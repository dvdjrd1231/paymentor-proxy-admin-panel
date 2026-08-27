<?php

namespace Paymenter\Extensions\Others\InvoiceOps\Support;

use App\Jobs\Server\TerminateJob;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Paymenter\Extensions\Others\InvoiceOps\Models\InvoiceRefund;

/**
 * The reference's **Refund** tab.
 *
 * Its fields, one for one: which transaction, how much (*"Leave blank for full refund"*),
 * the refund type, *"Reverse Payment — undo automated actions triggered by this transaction,
 * where possible"*, and whether to tell the customer.
 *
 * ## What this does not do
 *
 * It does not call the gateway. Paymenter has no refund contract — neither `ExtensionHelper`
 * nor any of the four gateways here defines one — so "Refund through Gateway" would mean
 * adding a `refund()` to Stripe, CoinPayments, Cryptomus and Binance and testing each
 * against real money. That is its own piece of work, and pretending to do it would be worse
 * than not: an administrator who believes the money went back when it did not is a chargeback
 * and a lost customer.
 *
 * So a refund here is **recorded**, not executed. You refund in the Stripe dashboard, or by
 * bank transfer, and record it — which is exactly what the reference's *"Refund Type"*
 * offers as its non-gateway option, and what most refunds on a crypto-heavy store are anyway.
 * The record is what makes the money visible: an invoice that has been given back, by whom,
 * for what reason, and whether the service went with it.
 */
class Refunds
{
    /** The reference's own invoice status for this. Nothing overdue ever matches it. */
    public const STATUS_REFUNDED = 'refunded';

    /**
     * Record a refund against an invoice.
     *
     * The invoice moves to `refunded` only when the whole of it has been given back. A part
     * refund leaves the status alone, because the invoice *was* paid and half-changing it
     * would make the books disagree with the bank.
     *
     * `reverseService` is the reference's *"undo automated actions"*: the service that was
     * paid for is cancelled and the panel told to release it. Off by default and never
     * implied — refunding a month's overpayment should not take the customer's proxy away.
     */
    public static function record(
        Invoice $invoice,
        ?float $amount,
        string $method,
        ?string $reason,
        bool $reverseService,
        ?User $admin = null,
    ): InvoiceRefund {
        // Blank means all of it, as the reference's placeholder says.
        $amount = $amount ?? (float) $invoice->total;

        return DB::transaction(function () use ($invoice, $amount, $method, $reason, $reverseService, $admin): InvoiceRefund {
            $refund = InvoiceRefund::create([
                'invoice_id' => $invoice->id,
                'transaction_id' => $invoice->transactions()->latest('id')->value('id'),
                'admin_id' => $admin?->id,
                'amount' => $amount,
                'currency_code' => $invoice->currency_code,
                'method' => $method,
                'reason' => $reason,
                'reversed_service' => $reverseService,
            ]);

            if (static::refundedInFull($invoice)) {
                $invoice->update(['status' => static::STATUS_REFUNDED]);
            }

            if ($reverseService) {
                static::reverse($invoice);
            }

            return $refund;
        });
    }

    /** Everything refunded so far against this invoice. */
    public static function refunded(Invoice $invoice): float
    {
        return (float) InvoiceRefund::where('invoice_id', $invoice->id)->sum('amount');
    }

    /**
     * Whether the whole invoice has been given back.
     *
     * A hundredth of a unit of tolerance, because these are decimals out of a database and
     * an exact float comparison would leave a fully refunded invoice one cent short of it.
     */
    public static function refundedInFull(Invoice $invoice): bool
    {
        return static::refunded($invoice) >= ((float) $invoice->total - 0.01);
    }

    /**
     * Undo what the payment set in motion: cancel the services this invoice paid for.
     *
     * Only services still running are touched. Nothing is un-refunded and nothing is
     * un-provisioned twice — the panel has released a cancelled service already, and telling
     * it to release again is a call about something it no longer holds.
     */
    private static function reverse(Invoice $invoice): void
    {
        $invoice->items()
            ->where('reference_type', Service::class)
            ->pluck('reference_id')
            ->each(function ($serviceId): void {
                $service = Service::find($serviceId);

                if (!$service || $service->status === Service::STATUS_CANCELLED) {
                    return;
                }

                $service->update(['status' => Service::STATUS_CANCELLED]);

                DB::afterCommit(fn () => TerminateJob::dispatch($service));
            });
    }
}
