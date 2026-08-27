<?php

namespace Paymenter\Extensions\Others\BillableItems;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use App\Helpers\ExtensionHelper;
use App\Models\Invoice;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Paymenter\Extensions\Others\BillableItems\Support\Items;
use Throwable;

/**
 * The reference's **Billable Items**: charging for something nobody ordered.
 *
 * Everything Paymenter can bill for has to be a product a customer bought. There is no way to
 * charge for a one-off — an hour of setup, a manual IP change, a block of addresses outside a
 * plan, a chargeback fee — without inventing a product for it and pretending they ordered
 * one. The reference's answer is a line you write against a customer, which lands on their
 * next invoice.
 *
 * ## The action that matters
 *
 * *"Add to User's Next Invoice"* is the default here as it is there, and it is the reason the
 * feature is worth having: a £5 charge on an invoice of its own costs more in payment fees
 * and attention than it collects. Hooking `Invoice::created` is what makes it true without
 * this module knowing anything about renewals — the renewal invoice the cron was going to
 * raise anyway simply arrives with an extra line.
 *
 * An item set to wait does not wait for ever. A customer with no recurring service would
 * never get another invoice, so anything that has waited twice the invoice lead time is given
 * one of its own.
 *
 * @link docs/modules/billable-items.md
 */
#[ExtensionMeta(
    name: 'Billable Items',
    description: 'Ad-hoc charges that land on a customer\'s next invoice.',
    version: '1.0.0',
    author: 'Paymenter Proxy Platform',
)]
class BillableItems extends Extension
{
    public function getConfig($values = [])
    {
        return [[
            'name' => 'Notice',
            'type' => 'placeholder',
            'label' => new HtmlString(
                'Charge a customer for something that is not a product — setup time, a manual change, a '
                . 'chargeback fee. Items are written under <b>Billing → Billable Items</b> and land on the '
                . 'customer\'s next invoice, or on one of their own if none is coming.'
            ),
        ]];
    }

    public function installed()
    {
        ExtensionHelper::runMigrations('extensions/Others/BillableItems/database/migrations');
    }

    public function uninstalled()
    {
        // Items already invoiced are on invoices, which are core's and stay. What goes is the
        // queue of uninvoiced ones — money not yet charged for, which is worth knowing before
        // disabling this.
        ExtensionHelper::rollbackMigrations('extensions/Others/BillableItems/database/migrations');
    }

    public function boot()
    {
        $this->rideAlongOnNewInvoices();
        $this->sweepDaily();
    }

    /**
     * "Add to the user's next invoice", implemented as the next invoice being created.
     *
     * `created` on the model rather than a domain event, for the same reason as everywhere
     * else here: core defines invoice events and does not dispatch them.
     */
    private function rideAlongOnNewInvoices(): void
    {
        Invoice::created(fn (Invoice $invoice) => Items::attachToNewInvoice($invoice));
    }

    /**
     * Daily, not every minute: this raises invoices, and an invoice is a thing a customer
     * reads. The reference does it on the same daily pass as everything else, and there is no
     * argument here for being faster — an item written at 4pm belongs on tomorrow's invoice
     * just as well as on one raised four minutes later.
     */
    private function sweepDaily(): void
    {
        app()->booted(function (): void {
            // Guarded because a throw here reaches no handler: `booted()` runs on every
            // request, so an exception while *registering* background work 500s every page
            // of the site — which is exactly what an `Artisan::starting()` that did not
            // exist did on 2026-08-27. A schedule that fails to register costs a background
            // task; an unhandled boot exception costs the whole business.
            try {
                app(Schedule::class)
                    ->call(fn () => Items::sweep())
                    ->dailyAt(config('settings.cronjob_time', '00:00'))
                    ->name('billable-items-sweep')
                    ->withoutOverlapping()
                    ->onOneServer();
            } catch (Throwable $exception) {
                Log::error('BillableItems: could not register its scheduled work', [
                    'exception' => $exception->getMessage(),
                ]);
            }
        });
    }
}
