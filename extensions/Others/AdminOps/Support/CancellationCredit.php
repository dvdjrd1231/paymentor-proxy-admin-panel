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
 */
class CancellationCredit
{
    /** Called from Eloquent's `updated` hook on Service. */
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

    /** Did the customer ask to cancel at the end of the period they had already paid for? */
    private static function isEndOfPeriod(Service $service): bool
    {
        try {
            return $service->cancellation()->where('type', 'end_of_period')->exists();
        } catch (\Throwable) {
            // No cancellation relation on this install: treat it as a direct cancellation.
            return false;
        }
    }

    /** The unused portion of what the customer paid for the current period. */
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
