<?php

namespace Paymenter\Extensions\Others\InvoiceOps\Support;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Paymenter\Extensions\Others\InvoiceOps\Models\InvoiceRefund;
use Paymenter\Extensions\Others\InvoiceOps\Models\RefundRequest;

/**
 * Answering a refund request.
 *
 * This is the shape that makes refunds workable without a gateway API, and it is the answer
 * to what blocked them. Paymenter cannot push money back through Stripe — no gateway here
 * defines a `refund()` — but the **decision** needs no API. An administrator approves here,
 * refunds in the gateway's own dashboard, and the approval writes the
 * {@see InvoiceRefund} that the ledger, the Transactions report and the Amount Out column
 * already read.
 *
 * The money and the record are therefore two acts by one person, in that order, and the
 * screen says so rather than implying the approval moved anything.
 */
class Requests
{
    /**
     * A customer asking for money back.
     *
     * Only a paid invoice can be refunded, and only once at a time: a second request while
     * one is still open is the same conversation twice, and answering both separately is how
     * a customer gets refunded twice.
     */
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

    /**
     * Approve: record the refund the administrator has just made in the gateway.
     *
     * `$amount` overrides what was asked for, because an administrator is not bound by the
     * request — a part refund is a legitimate answer to "give me all of it", and forcing a
     * yes/no on the customer's number would push those conversations off the record entirely.
     *
     * The request keeps the refund's id, which is what stops one request being approved into
     * two refunds if the button is pressed twice.
     */
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

    /**
     * Refuse, with a reason that is required rather than optional.
     *
     * A refusal with nothing behind it is the one a customer escalates, and the one nobody
     * can defend three months later when it comes back as a chargeback.
     */
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
