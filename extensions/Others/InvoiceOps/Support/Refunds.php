<?php

namespace Paymenter\Extensions\Others\InvoiceOps\Support;

use App\Jobs\Server\TerminateJob;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Paymenter\Extensions\Others\InvoiceOps\Models\InvoiceRefund;

/** The reference's **Refund** tab. */
class Refunds
{
    /** The reference's own invoice status for this. Nothing overdue ever matches it. */
    public const STATUS_REFUNDED = 'refunded';

    /** Record a refund against an invoice. */
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

    /** Whether the whole invoice has been given back. */
    public static function refundedInFull(Invoice $invoice): bool
    {
        return static::refunded($invoice) >= ((float) $invoice->total - 0.01);
    }

    /** Undo what the payment set in motion: cancel the services this invoice paid for. */
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
