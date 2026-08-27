<?php

namespace Paymenter\Extensions\Others\InvoiceOps\Support;

use App\Helpers\NotificationHelper;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Draft invoices — the reference's status, and the rule that makes it mean anything.
 *
 * > *"This is a Draft Invoice. The client is not able to see or access this invoice until it
 * > is published."*
 *
 * Paymenter has three statuses — `pending`, `paid`, `cancelled` — and **an invoice is visible
 * to the customer the instant it exists**. `App\Livewire\Invoices\Index` lists
 * `Auth::user()->invoices()` with no status filter at all. So building half an invoice, or
 * drafting one to check the figures, mails a customer a bill you were still writing.
 *
 * `invoices.status` is a plain string column, so `draft` needs no migration. What it needs is
 * the second half: something that actually hides it.
 *
 * ## How it is hidden
 *
 * A global scope on the model, added at boot, that excludes drafts **for everyone who is not
 * an administrator**. A scope rather than patching the two core components that list
 * invoices, because those are core and because they are not the only readers — the client
 * dashboard, the navigation badge and the theme all count invoices too, and each would have
 * to be found and fixed. One scope covers every query that will ever be written.
 *
 * It deliberately does **not** apply in the console. The daily cron reads invoices to chase
 * and settle them, and a scope that silently removed rows from a job nobody is watching is
 * exactly the kind of fault that surfaces months later as "why was this never invoiced". A
 * draft is skipped there on its status, visibly, not by disappearing.
 */
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

    /**
     * Administrators, and every console process.
     *
     * The console has no authenticated user, so without the first check the cron would run
     * with drafts hidden — see the class note.
     */
    public static function maySeeDrafts(): bool
    {
        if (app()->runningInConsole()) {
            return true;
        }

        return (bool) Auth::user()?->role_id;
    }

    /**
     * Publish: the invoice becomes the customer's problem.
     *
     * `pending` rather than a new state, because that is what an unpaid Paymenter invoice
     * already is — everything downstream, the overdue ladder, the reminders, the payment
     * page, is written against it. Draft is a state *before* the normal life of an invoice,
     * not beside it.
     */
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
     *
     * Paymenter has the templates and the machinery to render them; what it has no way to do
     * is fire one at an invoice deliberately. Everything is automatic, so an administrator
     * who wants to nudge one customer has to wait for the cron to nudge all of them.
     *
     * Returns false rather than throwing: this is a convenience on a screen, and a mail
     * server that is down should say so in a notification, not lose the page.
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
