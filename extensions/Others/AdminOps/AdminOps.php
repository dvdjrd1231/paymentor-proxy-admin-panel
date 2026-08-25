<?php

namespace Paymenter\Extensions\Others\AdminOps;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationBuilder;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * Admin-area usability, built to the WHMCS admin the client asked us to match.
 *
 * Paymenter's admin is a competent Filament panel, but it is organised around records
 * rather than around a working day: the dashboard reports 30-day trends, and answering
 * "what needs doing" or "who is this customer" means visiting several lists. This adds the
 * two things WHMCS gets right and Paymenter does not —
 *
 *  - a homepage that opens with today's figures, the work queue and the usual shortcuts;
 *  - a client summary, everything about one customer on one screen.
 *
 * All of it is additive. Nothing here replaces a core page, so disabling the extension
 * returns the panel to stock Paymenter — the one core touch is the **Summary** link on the
 * customer list, which is guarded by `class_exists` and documented as touchpoint #10.
 *
 * Adding that link from here was tried first and does not work: `Table::configureUsing()`
 * runs inside `Table::make()`, and the resource's own `table()` method runs afterwards with
 * `->recordActions([...])`, which resets the array before repopulating it. Anything pushed
 * from an extension is therefore discarded before the table renders. The same is true of
 * `->filters([...])`, which is why the action queue links to core's existing filters rather
 * than adding its own.
 *
 * @link docs/02b-admin-area.md
 */
#[ExtensionMeta(
    name: 'Admin Operations',
    description: 'WHMCS-style admin dashboard and per-customer summary screen.',
    version: '2.0.0',
    author: 'Paymenter Proxy Platform',
)]
class AdminOps extends Extension
{
    public function getConfig($values = [])
    {
        return [[
            'name' => 'Notice',
            'type' => 'placeholder',
            'label' => new HtmlString(
                'Adds the WHMCS-style admin dashboard (<b>At a glance</b>, <b>Needs attention</b>, '
                . 'shortcuts) and a per-customer <b>Summary</b> screen, reached from the '
                . '<b>Summary</b> link on each row of Clients. '
                . 'See <code>docs/02b-admin-area.md</code>.'
            ),
        ]];
    }

    public function boot()
    {
        View::addNamespace('adminops', __DIR__ . '/resources/views');

        $this->registerStyles();
        $this->registerWhmcsSkin();
        $this->keepTheDailyLogWritable();
    }

    /**
     * The WHMCS admin look: menu bar, left rail, panels, tables.
     *
     * Three pieces, all registered from here so the whole skin arrives and leaves with the
     * extension. Disable AdminOps and the panel is stock Filament again — including its
     * navigation, because {@see WhmcsNavigation} is only installed while this runs.
     *
     * The one thing it cannot do from here is `->topNavigation()`, which is a panel
     * construction-time call; that is the single core line, documented as touchpoint #11.
     */
    private function registerWhmcsSkin(): void
    {
        FilamentView::registerRenderHook(
            'panels::head.end',
            fn (): string => Blade::render('@include(\'adminops::skin\')'),
        );

        // Inside `.fi-layout`, immediately before Filament's own sidebar — which
        // `.fi-body-has-top-navigation` translates off-screen — so the rail becomes the
        // page's left column rather than a second one beside it.
        FilamentView::registerRenderHook(
            'panels::layout.start',
            fn (): string => Blade::render('@include(\'adminops::rail\')'),
        );

        // Core's own admin footer renders at `panels::sidebar.nav.end`, inside the sidebar
        // that top navigation moves off-screen, so it would never be seen again.
        FilamentView::registerRenderHook(
            'panels::footer',
            fn (): string => Blade::render('@include(\'adminops::footer\')'),
        );

        // Replaces the panel's whole navigation with WHMCS's menus. Registered through
        // `Filament::serving()` because the panel does not exist yet when extensions boot.
        Filament::serving(function (): void {
            Filament::getCurrentOrDefaultPanel()?->navigation(
                fn (NavigationBuilder $builder): NavigationBuilder => WhmcsNavigation::build($builder),
            );
        });
    }

    /**
     * Ship the widgets' CSS in the panel head.
     *
     * A render hook rather than a `<style>` block inside each widget view: Livewire
     * components must have a single root element, and polling re-renders the component, so
     * a style tag living in a widget would either break the root or be re-sent on every
     * poll.
     */
    private function registerStyles(): void
    {
        FilamentView::registerRenderHook(
            'panels::head.end',
            fn (): string => Blade::render('@include(\'adminops::styles\')'),
        );
    }

    /**
     * Force the day's log file to be group-writable.
     *
     * Whichever process writes the first line of the day owns the file. The scheduler and
     * artisan run as root, web requests as nginx, so on a day root got there first nginx
     * could not append — and since logging is part of handling the request, that surfaced
     * as 500s on the order pages with nothing recorded, the logger being what broke.
     *
     * Set here rather than in config/logging.php (where it also is) because config/ is not
     * bind-mounted into the container and extensions/ is. Remove once ./config is mounted.
     * Setting the mode beats chmod: it applies to whoever creates the file, and a chmod
     * only holds until midnight.
     */
    private function keepTheDailyLogWritable(): void
    {
        if (config('logging.channels.daily.permission') === null) {
            config(['logging.channels.daily.permission' => 0666]);
        }
    }
}
