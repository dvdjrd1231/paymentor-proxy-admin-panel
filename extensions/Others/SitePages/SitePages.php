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
 * The two remaining public pages the reference portal has in its menu bar.
 *
 * **Network Status** reuses published announcements as its incident feed, which is what the
 * reference portal does — its status page is a filtered announcement list, not a separate
 * system. With nothing published it shows the green all-clear.
 *
 * **Contact Us** routes people into the ticket system rather than at an unmonitored inbox,
 * so every request is tracked and answerable.
 *
 * Neither needs a table, so there are no migrations: this extension is pages and routes.
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

        // The Announcements extension ships views built on the *default* theme's Tailwind
        // components, which render a blue primary button — not the brand colour, and badly
        // out of place next to the rest of the client area. Prepending our directory to its
        // namespace makes these overrides win.
        //
        // Done on booted() rather than inline because extension boot order is not
        // guaranteed: if Announcements booted after us it would call addNamespace() and
        // replace the hints, silently undoing this.
        // `app()` rather than `$this->app`: the Extension base class is not a service
        // provider and has no $app property, and ExtensionHelper::call(mayFail: true)
        // swallows the resulting error — so the override silently never registered.
        $override = __DIR__ . '/resources/views/announcements';

        app()->booted(function () use ($override) {
            // Prepend first, and never call View::exists() before it: exists() resolves the
            // view through find(), which *caches* the path it lands on. Checking first
            // therefore cached the upstream file, and the later prepend changed the hints
            // while the stale resolution kept winning — the override silently did nothing.
            View::prependNamespace('announcements', $override);

            // Drop anything resolved earlier in this request so the new hint order applies.
            View::flushFinderCache();
        });

        // Menu entries append in the order their listeners are registered, so these three
        // are registered in the order the reference portal shows them:
        // … Knowledgebase, Network Status, Affiliates, Contact Us.
        //
        // Both of our pages always exist once enabled — unlike Knowledgebase or
        // Announcements, there is no "empty" state that would make an entry lead nowhere.
        Event::listen('navigation', fn () => [
            'name' => __('sitepages.network_status'),
            'route' => 'network-status',
            'icon' => 'ri-pulse-line',
        ]);

        // Affiliates ships with Paymenter but only registers itself in the account
        // dropdown, whereas the reference has it in the menu bar. Added here rather than by
        // editing that extension, so an upstream update cannot undo it. Guarded on the
        // route existing, so disabling Affiliates removes the entry instead of breaking
        // every page with a "route not defined" error.
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
