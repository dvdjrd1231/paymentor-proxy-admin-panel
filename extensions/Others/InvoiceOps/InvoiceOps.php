<?php

namespace Paymenter\Extensions\Others\InvoiceOps;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use App\Helpers\ExtensionHelper;
use Illuminate\Support\HtmlString;
use Paymenter\Extensions\Others\InvoiceOps\Support\Drafts;

/**
 * Draft invoices, refunds, and sending an invoice notice by hand.
 *
 * ## Draft — the one that changes behaviour
 *
 * The reference says it plainly on every draft: *"The client is not able to see or access
 * this invoice until it is published."* Paymenter has no such state, and
 * `App\Livewire\Invoices\Index` lists `Auth::user()->invoices()` with **no status filter**.
 * An invoice is therefore visible the instant it exists — so drafting one to check the
 * figures shows the customer a bill you were still writing.
 *
 * `invoices.status` is a plain string column, so `draft` costs no migration. What it costs is
 * a global scope: see {@see Drafts::hideFromCustomers()} for why a scope rather than patching
 * the components that list invoices, and why it does not apply in the console.
 *
 * ## Refunds
 *
 * Recorded, not executed — Paymenter has no refund contract for gateways, and pretending the
 * money moved when it did not is worse than not offering it. {@see Support\Refunds}.
 *
 * ## Sending a notice
 *
 * Core has fifteen invoice templates and no way to fire one at a single invoice; everything
 * is the cron's. The reference puts that dropdown beside the invoice status, and so does
 * **Billing → Invoice Operations**.
 *
 * @link docs/modules/invoice-ops.md
 */
#[ExtensionMeta(
    name: 'Invoice Operations',
    description: 'Draft invoices, recorded refunds, and sending an invoice notice by hand.',
    version: '1.0.0',
    author: 'Paymenter Proxy Platform',
)]
class InvoiceOps extends Extension
{
    public function getConfig($values = [])
    {
        return [[
            'name' => 'Notice',
            'type' => 'placeholder',
            'label' => new HtmlString(
                'Adds the <b>draft</b> invoice status — without it, a Paymenter invoice is visible to the '
                . 'customer the moment it is created — plus recorded refunds and a way to send one invoice '
                . 'notice by hand. Operations live under <b>Billing → Invoice Operations</b>.'
            ),
        ]];
    }

    public function installed()
    {
        ExtensionHelper::runMigrations('extensions/Others/InvoiceOps/database/migrations');
    }

    public function uninstalled()
    {
        // The refund records go. Any invoice left on `draft` or `refunded` keeps that status
        // — both are strings core tolerates, and rewriting a customer's billing history on
        // an uninstall would be the more surprising of the two behaviours. A draft does
        // become visible again, which is worth knowing before disabling this.
        ExtensionHelper::rollbackMigrations('extensions/Others/InvoiceOps/database/migrations');
    }

    public function boot()
    {
        Drafts::hideFromCustomers();
    }
}
