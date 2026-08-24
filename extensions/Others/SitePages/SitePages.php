<?php

namespace Paymenter\Extensions\Others\SitePages;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use Livewire\Livewire;
use Paymenter\Extensions\Others\SitePages\Livewire\ContactUs;
use Paymenter\Extensions\Others\SitePages\Livewire\NetworkStatus;

/**
 * The two public pages the reference portal has in its menu bar.
 *
 * Network Status reuses published announcements as its incident feed, as the reference does;
 * with none published it shows the all-clear. Contact Us routes into the ticket system
 * rather than an unmonitored inbox. Neither needs a table, so there are no migrations.
 *
 * @link docs/modules/site-pages.md
 */
#[ExtensionMeta(
    name: 'Site Pages',
    description: 'Network Status and Contact Us pages for the public menu.',
    version: '1.0.0',
    author: 'Paymenter Proxy Platform',
)]
class SitePages extends Extension
{
    public function getConfig($values = [])
    {
        return [
            [
                'name' => 'Notice',
                'type' => 'placeholder',
                'label' => new HtmlString(
                    'Adds <b>Network Status</b> (<code>/network-status</code>) and '
                    . '<b>Contact Us</b> (<code>/contact</code>) to the public menu. '
                    . 'Network Status lists published announcements as incidents, so post an '
                    . 'announcement to report one; with none published it shows the all-clear.'
                ),
            ],
        ];
    }

    public function boot()
    {
        require __DIR__ . '/routes/web.php';

        View::addNamespace('sitepages', __DIR__ . '/resources/views');

        Livewire::component('sitepages.network-status', NetworkStatus::class);
        Livewire::component('sitepages.contact', ContactUs::class);

        // Announcements ships views built on the default theme's Tailwind components, which
        // look wrong in this theme. Prepending our directory to its namespace overrides them.
        //
        // On booted(), not inline: extension boot order is not guaranteed, and Announcements
        // booting later would call addNamespace() and replace the hints. `app()` rather than
        // `$this->app` because Extension is not a service provider.
        $override = __DIR__ . '/resources/views/announcements';

        app()->booted(function () use ($override) {
            // Never call View::exists() before this: exists() resolves through find(), which
            // caches the path it lands on, so the upstream file wins despite the prepend.
            View::prependNamespace('announcements', $override);
            View::flushFinderCache();       // drop anything resolved earlier this request
        });

        // Menu entries append in listener-registration order, so these follow the reference:
        // Knowledgebase, Network Status, Affiliates, Contact Us.
        Event::listen('navigation', fn () => [
            'name' => __('sitepages.network_status'),
            'route' => 'network-status',
            'icon' => 'ri-pulse-line',
        ]);

        // Affiliates only registers itself in the account dropdown; the reference has it in
        // the menu bar. Added here so an upstream update cannot undo it, and guarded on the
        // route so disabling Affiliates removes the entry rather than 500-ing every page.
        Event::listen('navigation', function () {
            if (!Route::has('affiliate.index')) {
                return;
            }

            return [
                'name' => __('sitepages.affiliates'),
                'route' => 'affiliate.index',
                'icon' => 'ri-share-line',
            ];
        });

        Event::listen('navigation', fn () => [
            'name' => __('sitepages.contact_us'),
            'route' => 'contact',
            'icon' => 'ri-mail-line',
        ]);
    }
}
