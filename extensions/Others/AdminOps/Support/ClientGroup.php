<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * What belonging to a client group actually does (Leandro, 2026-09-07: "these group will
 * this condition as common. this is client group").
 *
 * The screen stores three settings; this is where two of them become real. The third,
 * Exempt from Suspend & Terminate, is read by {@see ServiceOverrides} on the sweep that
 * already handles suspension, so it stays there rather than being duplicated here.
 *
 * ## Group Discount %
 *
 * Applied as its own invoice line with a negative price, the same shape
 * {@see \Paymenter\Extensions\Others\PaymentFees\PaymentFees::applyFee} uses for a
 * gateway fee, and for the same reasons: the reduction is visible to the customer on the
 * invoice instead of silently baked into a product's price, and it can be recomputed from
 * scratch whenever the invoice changes.
 *
 * Recomputed on every item change rather than once at the end. `Invoice\Finalized` looked
 * like the natural hook — it fires after the response, with every item present — but
 * core's own mail listener is on that event too, so a discount added there races the
 * "invoice created" email and can send a total that is already wrong.
 *
 * ## Separate Invoices for Services
 *
 * Paymenter already bills **one invoice per service**: the renewal cron loops over due
 * services and creates an invoice inside the loop. So a group with this ticked matches
 * what the platform does anyway, and the setting only has work to do when it is *off* —
 * that is the case this merges, folding a client's pending service invoices of the same
 * currency into the oldest one.
 *
 * Merging is deliberately conservative. An invoice that has any transaction against it is
 * never touched — money has been recorded against that number — and an emptied invoice is
 * cancelled rather than deleted, so its number still resolves for anyone holding it.
 */
class ClientGroup
{
    /**
     * Marks our own line so it can be found and rebuilt without touching anything else.
     *
     * On `reference_type`, not in the description: the description is the only free text
     * on an invoice item and the customer reads it, so a `[group-discount]` prefix — the
     * shape the payment fee uses — would print on their invoice. A morph reference is
     * invisible there, exact to match on, and already how a line points at what it bills.
     */
    public const MARKER = \Paymenter\Extensions\Others\AdminOps\Models\ClientGroup::class;

    /** The group a client belongs to, or null. */
    public static function forUser(?int $userId): ?object
    {
        if (!$userId || !Schema::hasTable('ext_client_groups')) {
            return null;
        }

        $id = DB::table('properties')
            ->where('model_type', User::class)->where('model_id', $userId)
            ->where('key', 'client_group_id')->value('value');

        return $id ? DB::table('ext_client_groups')->find((int) $id) : null;
    }

    /**
     * Rebuild the discount line for an invoice. Idempotent: the previous line is removed
     * first, so this can run on every item change without compounding.
     *
     * @return float The discount applied, as a positive number.
     */
    public static function applyDiscount(Invoice $invoice): float
    {
        $group = static::forUser($invoice->user_id);

        $invoice->items()->where('reference_type', self::MARKER)->delete();
        $invoice->refresh();
        $invoice->load('items');

        if (!$group || (float) $group->discount_percent <= 0) {
            return 0.0;
        }

        // Charges only. A negative line is somebody else's credit and must not be
        // discounted a second time.
        $subtotal = $invoice->items
            ->filter(fn ($item) => (float) $item->price > 0)
            ->sum(fn ($item) => (float) $item->price * max(1, (int) $item->quantity));

        $discount = round($subtotal * (float) $group->discount_percent / 100, 2);

        if ($discount <= 0) {
            return 0.0;
        }

        $invoice->items()->create([
            'description' => $group->name . ' discount ('
                . rtrim(rtrim(number_format((float) $group->discount_percent, 2), '0'), '.') . '%)',
            'price' => -$discount,
            'quantity' => 1,
            'reference_type' => self::MARKER,
            'reference_id' => $group->id,
        ]);

        $invoice->refresh();

        return $discount;
    }

    /**
     * Fold each affected client's pending service invoices into one.
     *
     * Only clients in a group with Separate Invoices **off** are considered — everyone
     * else keeps Paymenter's own one-invoice-per-service behaviour, which is what the
     * setting describes when it is on.
     *
     * @return array{merged: int, invoices: int}
     */
    public static function mergeInvoices(): array
    {
        if (!Schema::hasTable('ext_client_groups')) {
            return ['merged' => 0, 'invoices' => 0];
        }

        $groups = DB::table('ext_client_groups')->where('separate_invoices', false)->pluck('id');

        if ($groups->isEmpty()) {
            return ['merged' => 0, 'invoices' => 0];
        }

        $userIds = DB::table('properties')
            ->where('model_type', User::class)->where('key', 'client_group_id')
            ->whereIn('value', $groups->map(fn ($id) => (string) $id))
            ->pluck('model_id');

        $merged = 0;
        $folded = 0;

        foreach ($userIds as $userId) {
            $byCurrency = Invoice::where('user_id', $userId)->where('status', 'pending')
                ->with(['items', 'transactions'])->orderBy('id')->get()
                ->groupBy('currency_code');

            foreach ($byCurrency as $invoices) {
                // Never touch an invoice money has been recorded against.
                $invoices = $invoices->filter(fn (Invoice $i) => $i->transactions->isEmpty())->values();

                if ($invoices->count() < 2) {
                    continue;
                }

                try {
                    DB::transaction(function () use ($invoices, &$merged, &$folded): void {
                        $target = $invoices->first();

                        foreach ($invoices->skip(1) as $invoice) {
                            $invoice->items()->update(['invoice_id' => $target->id]);
                            // Cancelled, not deleted: the number may already be in
                            // somebody's inbox, and it must still resolve.
                            $invoice->update(['status' => 'cancelled']);
                            $folded++;
                        }

                        // The discount is a percentage of the invoice it sits on, so it
                        // has to be recomputed once the lines have moved.
                        static::applyDiscount($target->refresh());
                        $merged++;
                    });
                } catch (\Throwable $exception) {
                    Log::error('AdminOps: could not merge client invoices', [
                        'user' => $userId, 'error' => $exception->getMessage(),
                    ]);
                }
            }
        }

        return ['merged' => $merged, 'invoices' => $folded];
    }
}
