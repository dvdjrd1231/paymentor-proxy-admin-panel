<?php

namespace Paymenter\Extensions\Others\InvoiceOps\Support;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Paymenter\Extensions\Others\InvoiceOps\Models\InvoiceRefund;
use Paymenter\Extensions\Others\InvoiceOps\Models\RefundRequest;

/** Answering a refund request. */
class Requests
{
    /** A customer asking for money back. */
    public static function open(Invoice $invoice, ?float $amount, string $reason): ?RefundRequest
    {
        if ($invoice->status !== Invoice::STATUS_PAID) {
            return null;
        }

        $existing = RefundRequest::where('invoice_id', $invoice->id)
            ->where('status', RefundRequest::STATUS_PENDING)
            ->first();

        if ($existing) {
            return $existing;
        }

        return RefundRequest::create([
            'invoice_id' => $invoice->id,
            'user_id' => $invoice->user_id,
            'amount' => $amount,
            'reason' => $reason,
        ]);
    }

    /** Approve: record the refund the administrator has just made in the gateway. */
    public static function approve(
        RefundRequest $request,
        ?float $amount,
        string $method,
        ?string $note,
        bool $reverseService,
        ?User $admin = null,
    ): ?InvoiceRefund {
        if (!$request->isPending() || !$request->invoice) {
            return null;
        }

        return DB::transaction(function () use ($request, $amount, $method, $note, $reverseService, $admin): InvoiceRefund {
            $refund = Refunds::record(
                $request->invoice,
                $amount ?? ($request->amount !== null ? (float) $request->amount : null),
                $method,
                // The customer's reason is what this refund is *for*; the administrator's
                // note is why it was allowed. Both belong on the record, so both are kept.
                trim(($request->reason ?? '') . ($note ? ' — ' . $note : '')),
                $reverseService,
                $admin,
            );

            $request->update([
                'status' => RefundRequest::STATUS_APPROVED,
                'admin_id' => $admin?->id,
                'decision_note' => $note,
                'decided_at' => now(),
                'refund_id' => $refund->id,
            ]);

            return $refund;
        });
    }

    /** Refuse, with a reason that is required rather than optional. */
    public static function refuse(RefundRequest $request, string $note, ?User $admin = null): void
    {
        if (!$request->isPending()) {
            return;
        }

        $request->update([
            'status' => RefundRequest::STATUS_REFUSED,
            'admin_id' => $admin?->id,
            'decision_note' => $note,
            'decided_at' => now(),
        ]);
    }
}
