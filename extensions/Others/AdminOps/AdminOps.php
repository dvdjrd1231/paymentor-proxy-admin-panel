<?php

namespace Paymenter\Extensions\Others\AdminOps;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use App\Helpers\ExtensionHelper;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationBuilder;
use Filament\Support\Facades\FilamentView;
use Illuminate\Auth\Events\Login as AuthLogin;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use Paymenter\Extensions\Others\AdminOps\Support\PanelSession;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * The WHMCS-style admin: dashboard, per-customer summary, and the panel skin.
 *
 * Entirely additive — disabling this returns the panel to stock Paymenter.
 *
 * Trap: a resource's table cannot be extended from here. `Table::configureUsing()` runs
 * inside `Table::make()`, before the resource's own `table()` resets `recordActions` and
 * `filters`, so anything pushed from an extension is discarded. Hence the Summary link is
 * core touchpoint #10, and the action queue reuses core's filters.
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

    /**
     * Creates `ext_adminops_dashboard_layouts` — one row per admin, holding the order of
     * their dashboard panels and which they have put away. {@see Admin\Widgets\DashboardTools}
     * checks the table exists before rendering, so an enabled-but-unmigrated install falls
     * back to the stock dashboard rather than fatalling on the panel's home page.
     */
    public function installed()
    {
        ExtensionHelper::runMigrations('extensions/Others/AdminOps/database/migrations');
    }

    public function uninstalled()
    {
        ExtensionHelper::rollbackMigrations('extensions/Others/AdminOps/database/migrations');
    }

    public function boot()
    {
        View::addNamespace('adminops', __DIR__ . '/resources/views');

        $this->registerStyles();
        $this->registerWhmcsSkin();
        $this->keepSignInsRecorded();
        $this->keepTheDailyLogWritable();
        $this->registerQuotePdf();
    }

    /**
     * The quote PDF, for {@see Admin\Pages\CreateQuote}'s View/Download/Printable buttons —
     * core's own dompdf, a quote template instead of the invoice one. Admin-only: same
     * permission that reads invoices, checked inside because route middleware cannot know
     * the panel's guard at this point in boot.
     */
    private function registerQuotePdf(): void
    {
        \Illuminate\Support\Facades\Route::middleware(['web'])->get('/admin/quote-pdf/{quote}', function (int $quote) {
            abort_unless((bool) \Illuminate\Support\Facades\Auth::user()?->hasPermission('admin.invoices.viewAny'), 403);
            abort_unless(class_exists(\Paymenter\Extensions\Others\Quotes\Models\Quote::class), 404);

            $record = \Paymenter\Extensions\Others\Quotes\Models\Quote::with(['items', 'user.properties'])->findOrFail($quote);
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('adminops::pdf.quote', ['quote' => $record]);
            $name = 'quote-' . $record->id . '.pdf';

            return request()->boolean('inline') ? $pdf->stream($name) : $pdf->download($name);
        })->name('adminops.quote-pdf');
    }

    /**
     * Safety net: Paymenter signs out any user with no `user_sessions` row, so a sign-in path
     * that does not create one silently breaks the panel. See {@see PanelSession}.
     */
    private function keepSignInsRecorded(): void
    {
        Event::listen(AuthLogin::class, [PanelSession::class, 'issueMissingToken']);
    }

    /**
     * The WHMCS look: menu bar, left rail, panels, tables — all registered here so the skin
     * arrives and leaves with the extension. `->topNavigation()` is the one part that cannot
     * be, being a panel construction-time call: core touchpoint #11.
     */
    private function registerWhmcsSkin(): void
    {
        FilamentView::registerRenderHook(
            'panels::head.end',
            fn (): string => Blade::render('@include(\'adminops::skin\')'),
        );

        // The `+` sits between brand and menus; the utility icons after the search field.
        FilamentView::registerRenderHook(
            'panels::topbar.logo.after',
            fn (): string => Blade::render('@include(\'adminops::quick-create\')'),
        );

        FilamentView::registerRenderHook(
            'panels::global-search.after',
            fn (): string => Blade::render('@include(\'adminops::toolbar\')'),
        );

        // First child of `.fi-layout`, so the rail becomes the page's left column rather than
        // a second one beside Filament's own (which top navigation moves off-screen).
        FilamentView::registerRenderHook(
            'panels::layout.start',
            fn (): string => Blade::render('@include(\'adminops::rail\')'),
        );

        // `body.end`, not `panels::footer`: that hook fires inside the content column, so the
        // bar would start at the rail instead of spanning the window — and on the sign-in
        // page, whose layout is a centred column, it rendered as a short floating bar.
        FilamentView::registerRenderHook(
            'panels::body.end',
            fn (): string => Blade::render('@include(\'adminops::footer\')'),
        );

        // Via `Filament::serving()` because the panel does not exist yet when extensions boot.
        Filament::serving(function (): void {
            Filament::getCurrentOrDefaultPanel()?->navigation(
                fn (NavigationBuilder $builder): NavigationBuilder => WhmcsNavigation::build($builder),
            );
        });
    }

    /**
     * The widgets' CSS, in the panel head rather than inside each widget: a Livewire component
     * needs a single root, and polling would re-send an inline `<style>` on every refresh.
     */
    private function registerStyles(): void
    {
        FilamentView::registerRenderHook(
            'panels::head.end',
            fn (): string => Blade::render('@include(\'adminops::styles\')'),
        );
    }

    /**
     * The day's log file is owned by whichever process writes to it first — root (scheduler,
     * artisan) or nginx (web). When root won, nginx could not append, and the failed write
     * surfaced as intermittent 500s with an empty log. Duplicated from config/logging.php
     * because config/ is not bind-mounted into the container; remove once it is.
     */
    private function keepTheDailyLogWritable(): void
    {
        if (config('logging.channels.daily.permission') === null) {
            config(['logging.channels.daily.permission' => 0666]);
        }
    }
}
