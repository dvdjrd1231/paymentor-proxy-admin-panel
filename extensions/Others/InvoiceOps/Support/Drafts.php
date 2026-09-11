<?php

namespace Paymenter\Extensions\Others\InvoiceOps\Support;

use App\Helpers\NotificationHelper;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/** Draft invoices — the reference's status, and the rule that makes it mean anything. */
class Drafts
{
    public const STATUS = 'draft';

    /** Hide drafts from customers. Registered once, from the extension's `boot()`. */
    public static function hideFromCustomers(): void
    {
        Invoice::addGlobalScope('adminops-drafts', function (Builder $query): void {
            if (static::maySeeDrafts()) {
                return;
            }

            // `getTable()` rather than a bare column: this scope runs inside joins the
            // dashboard builds, where an unqualified `status` is ambiguous.
            $query->where($query->getModel()->getTable() . '.status', '!=', static::STATUS);
        });
    }

    /** Administrators, and every console process. */
    public static function maySeeDrafts(): bool
    {
        if (app()->runningInConsole()) {
            return true;
        }

        return (bool) Auth::user()?->role_id;
    }

    /** Publish: the invoice becomes the customer's problem. */
    public static function publish(Invoice $invoice, bool $sendEmail = false): void
    {
        if ($invoice->status !== static::STATUS) {
            return;
        }

        $invoice->update(['status' => Invoice::STATUS_PENDING]);

        if ($sendEmail) {
            static::send($invoice, 'new_invoice_created');
        }
    }

    /**
     * Send one of the invoice notices by hand — the reference's template dropdown and its
     * **Send Email** button, beside the status on every invoice.
     */
    public static function send(Invoice $invoice, string $template): bool
    {
        try {
            NotificationHelper::sendNotification($template, ['invoice' => $invoice], $invoice->user);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
