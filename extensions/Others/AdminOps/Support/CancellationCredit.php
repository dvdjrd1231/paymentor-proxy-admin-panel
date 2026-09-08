<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

use App\Models\Credit;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Paymenter\Extensions\Others\AdminOps\Models\Refund;

/**
 * Credit the unused part of a service's paid period back to the customer when the service
 * is cancelled (Leandro, 2026-09-08: "If client close or finish server service, the credit
 * would be return to client balance").
 *
 * This issues money without anyone pressing anything, so every rule it applies is written
 * down here rather than left to be inferred from the arithmetic.
 *
 * ## When it pays
 *
 * 1. The service's status has just become `cancelled`. Nothing else triggers it.
 * 2. The cancellation is **not** `end_of_period`. This is the reference's own logic, and it
 *    is the rule this class was missing: an end-of-period cancellation lets the customer go
 *    on using the service until it expires. They consume exactly what they paid for, so
 *    crediting it as well would hand back the service *and* the money. Only an immediate
 *    cancellation — or an admin ending a service outright, which has no request row —
 *    leaves time paid for and not used.
 * 3. `expires_at` is in the future — there is genuinely unused time. A service cancelled
 *    after its period ran out has nothing left to give back.
 * 3. The plan is recurring. `billingDuration` is 0 for free and one-time plans, and a
 *    one-time purchase has no period to prorate.
 * 4. The service has at least one **paid** invoice. Without that nothing was ever taken,
 *    so there is nothing to return — this is what keeps a service terminated for
 *    non-payment from being rewarded with credit.
 * 5. No refund has been recorded for this service before. The event can fire twice; a
 *    customer must not be paid twice.
 *
 * ## What it pays
 *
 * The recurring price for the unused days, pro rata:
 *
 *     price × quantity × (days remaining ÷ days in the billing period)
 *
 * The period is measured from the service's own dates — `expires_at` back one billing
 * duration — rather than from a nominal month, so a 30-day plan that actually ran 31 days
 * prorates against the 31.
 *
 * Setup fees are not included. They pay for work already done at provisioning time; there
 * is no unused portion of them to return.
 *
 * ## Switching it off
 *
 * `config('settings.credits_on_cancellation')`, on General Settings → Credit. On by
 * request, but a store that settles cancellations by hand can turn it off without touching
 * code.
 */
class CancellationCredit
{
    /**
     * Called from Eloquent's `updated` hook on Service.
     *
     * Not from `App\Events\Service\Updated` — core declares that event and never fires it,
     * so listening to it would be listening for something that does not happen.
     */
    public static function handle(Service $service): void
    {
        try {
            static::issue($service);
        } catch (\Throwable $exception) {
            // A cancellation must never fail because the credit could not be worked out.
            // The service is already cancelled by this point; losing the credit is a thing
            // to fix afterwards, throwing here would leave the customer's cancellation
            // half-done.
            Log::error('AdminOps: cancellation credit failed', [
                'service' => $service->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private static function issue(Service $service): void
    {
        if (!config('settings.credits_on_cancellation', true)) {
            return;
        }

        // Only the transition into cancelled, not every save of an already-cancelled row.
        if (!$service->wasChanged('status') || $service->status !== Service::STATUS_CANCELLED) {
            return;
        }

        if (Refund::issuedForService($service->id)) {
            return;
        }

        // End-of-period cancellations keep running until expiry, so there is nothing
        // unused to give back — see rule 2 in the class docblock.
        if (static::isEndOfPeriod($service)) {
            return;
        }

        $amount = static::unusedAmount($service);

        if ($amount <= 0) {
            return;
        }

        // The invoice the credit is recorded against: the most recent one this service was
        // actually paid on. No paid invoice means nothing was taken and nothing is owed
        // back — rule 4 above, and the reason a non-payment termination credits nothing.
        // Columns qualified: `invoices()` joins `invoice_items`, so a bare `id` is
        // ambiguous and MariaDB refuses the query outright.
        $invoiceId = $service->invoices()
            ->where('invoices.status', 'paid')
            ->orderByDesc('invoices.id')
            ->value('invoices.id');

        if (!$invoiceId) {
            return;
        }

        DB::transaction(function () use ($service, $amount, $invoiceId): void {
            // Re-check inside the lock: two events racing would otherwise both see "no
            // refund yet" and both pay.
            if (Refund::where('service_id', $service->id)->lockForUpdate()->exists()) {
                return;
            }

            $credit = Credit::firstOrCreate(
                ['user_id' => $service->user_id, 'currency_code' => $service->currency_code],
                ['amount' => 0],
            );
            $credit->increment('amount', $amount);

            Refund::create([
                'invoice_id' => $invoiceId,
                'service_id' => $service->id,
                'user_id' => $service->user_id,
                'amount' => $amount,
                'currency_code' => $service->currency_code,
                'reason' => 'Service cancelled — credit for the unused period to '
                    . $service->expires_at?->format('Y-m-d'),
                // Nobody authorised this one by hand.
                'admin_id' => null,
            ]);
        });
    }

    /**
     * Did the customer ask to cancel at the end of the period they had already paid for?
     *
     * `service_cancellations.type` is `immediate` or `end_of_period`. No request row at all
     * means an administrator ended the service directly, which stops it now — so that is
     * treated as immediate and does earn a credit.
     */
    private static function isEndOfPeriod(Service $service): bool
    {
        try {
            return $service->cancellation()->where('type', 'end_of_period')->exists();
        } catch (\Throwable) {
            // No cancellation relation on this install: treat it as a direct cancellation.
            return false;
        }
    }

    /**
     * The unused portion of what the customer paid for the current period.
     *
     * Returns 0.0 whenever the answer is not clearly positive — an expired service, a
     * one-time plan, a missing date. Silence is the right failure here: paying nothing is
     * recoverable by hand, paying the wrong amount automatically is not.
     */
    public static function unusedAmount(Service $service): float
    {
        $expires = $service->expires_at;

        if (!$expires || !$expires->isFuture()) {
            return 0.0;
        }

        $periodDays = (int) ($service->plan?->billingDuration ?? 0);

        if ($periodDays <= 0) {
            return 0.0;
        }

        // Measured from the service's own dates rather than a nominal month.
        $periodStart = $expires->copy()->subDays($periodDays);
        $totalDays = $periodStart->diffInDays($expires);

        if ($totalDays <= 0) {
            return 0.0;
        }

        $remainingDays = min(now()->diffInDays($expires, false), $totalDays);

        if ($remainingDays <= 0) {
            return 0.0;
        }

        $paid = (float) $service->price * max(1, (int) $service->quantity);

        return round($paid * ($remainingDays / $totalDays), 2);
    }
}
