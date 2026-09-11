<?php

namespace Paymenter\Extensions\Others\InvoiceOps;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use App\Helpers\ExtensionHelper;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use Paymenter\Extensions\Others\InvoiceOps\Support\Drafts;

/**
 * Draft invoices, refunds, and sending an invoice notice by hand.
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
        View::addNamespace('invoiceops', __DIR__ . '/resources/views');

        Drafts::hideFromCustomers();
    }
}
