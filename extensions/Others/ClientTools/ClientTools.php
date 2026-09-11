<?php

namespace Paymenter\Extensions\Others\ClientTools;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use App\Helpers\ExtensionHelper;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use Livewire\Livewire;
use Paymenter\Extensions\Others\ClientTools\Livewire\Addons;
use Paymenter\Extensions\Others\ClientTools\Livewire\ApplyCredit;
use Paymenter\Extensions\Others\ClientTools\Livewire\Contacts;
use Paymenter\Extensions\Others\ClientTools\Livewire\EmailHistory;
use Paymenter\Extensions\Others\ClientTools\Livewire\MassPayment;
use Paymenter\Extensions\Others\ClientTools\Livewire\Quotes;
use Paymenter\Extensions\Others\ClientTools\Livewire\UserManagement;

/**
 * The client-area pages the reference portal has that Paymenter does not ship.
 *
 * @link docs/modules/client-tools.md
 */
#[ExtensionMeta(
    name: 'Client Tools',
    description: 'Quotes, Mass Payment, Contacts, User Management, Email History and Addons pages.',
    version: '1.0.0',
    author: 'Paymenter Proxy Platform',
)]
class ClientTools extends Extension
{
    public function getConfig($values = [])
    {
        return [
            [
                'name' => 'Notice',
                'type' => 'placeholder',
                'label' => new HtmlString(
                    'Adds the reference portal\'s remaining client-area pages: '
                    . '<b>Quotes</b>, <b>Mass Payment</b> (<code>/billing/mass-payment</code>), '
                    . '<b>Contacts</b>, <b>User Management</b>, <b>Email History</b> and '
                    . '<b>Addons</b>, plus the <b>Apply Credit</b> panel on invoices.<br><br>'
                    . 'Contacts are added by customers themselves. Quotes always reads '
                    . 'empty — Paymenter has no quoting system, so the page shows the same '
                    . 'empty state the reference does rather than inventing data.'
                ),
            ],
        ];
    }

    public function installed()
    {
        ExtensionHelper::runMigrations('extensions/Others/ClientTools/database/migrations');
    }

    public function uninstalled()
    {
        ExtensionHelper::rollbackMigrations('extensions/Others/ClientTools/database/migrations');
    }

    public function boot()
    {
        require __DIR__ . '/routes/web.php';

        View::addNamespace('clienttools', __DIR__ . '/resources/views');

        Livewire::component('clienttools.quotes', Quotes::class);
        Livewire::component('clienttools.mass-payment', MassPayment::class);
        Livewire::component('clienttools.contacts', Contacts::class);
        Livewire::component('clienttools.user-management', UserManagement::class);
        Livewire::component('clienttools.email-history', EmailHistory::class);
        Livewire::component('clienttools.addons', Addons::class);
        Livewire::component('clienttools.apply-credit', ApplyCredit::class);

        // Nothing is contributed to the public menu bar: every page here is reached from
        // the signed-in Billing/Support/Account menus, which the theme's navigation
        // component lays out explicitly.
    }
}
