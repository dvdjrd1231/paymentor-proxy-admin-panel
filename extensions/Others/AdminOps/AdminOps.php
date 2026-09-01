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
        // The skin's CSS now rides in the one stylesheet registered by registerStyles();
        // only the markup hooks below remain here.

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
    /**
     * The panel is light, whatever the browser prefers.
     *
     * Filament stamps `class="dark"` on `<html>` when the OS or browser asks for dark, and
     * every `dark:` rule in its compiled stylesheet then fires. Our skin repaints the
     * surfaces it owns, but not Filament's own components — so on a dark-themed Chrome the
     * login card, the inputs and assorted panels came out dark against a white page. The
     * reference does not do this: WHMCS renders its own colours and ignores the browser.
     *
     * Removing the class is the whole fix — with it gone, not one `dark:` rule matches.
     * It runs at `head.start`, before Filament's own theme script and before first paint,
     * so there is no flash of the wrong theme; `theme` is pinned in localStorage so
     * Filament's script does not put the class back, and the two events cover SPA
     * navigation, where the document is never reloaded.
     */
    private function keepThePanelLight(): void
    {
        FilamentView::registerRenderHook('panels::head.start', fn (): string => <<<'HTML'
            <script>
                (() => {
                    const light = () => {
                        document.documentElement.classList.remove('dark');
                        document.documentElement.style.colorScheme = 'light';
                    };
                    try { localStorage.setItem('theme', 'light'); } catch (e) {}
                    light();
                    document.addEventListener('DOMContentLoaded', light);
                    document.addEventListener('livewire:navigated', light);
                })();
            </script>
            HTML);
    }

    private function registerStyles(): void
    {
        $this->keepThePanelLight();

        // The skin and the widget styles are 160 KB of CSS between them. Injected inline
        // they rode in the <head> of every response — never cached, re-parsed on every
        // full page load, and counted against every HTML payload. Served as one file with
        // a content-hashed URL the browser fetches them once and reuses them until they
        // actually change. {@see registerSkinStylesheet}.
        $this->registerSkinStylesheet();

        FilamentView::registerRenderHook(
            'panels::head.end',
            // The stylesheet by reference; the behaviour still inline. The skin blade
            // carries the menu's flyout script as well as its CSS, and that script has to
            // stay in the document — serving it as part of a .css file both broke the
            // submenus and appended dead text to the sheet.
            fn (): string => '<link rel="stylesheet" href="' . e(static::styleUrl()) . '">' . static::styleScripts(),
        );
    }

    /** The `<script>` blocks of the style blades, for inlining in the head. */
    public static function styleScripts(): string
    {
        $out = '';

        foreach (['skin', 'styles'] as $part) {
            $rendered = Blade::render('@include(\'adminops::' . $part . '\')');

            if (preg_match_all('#<script\b[^>]*>.*?</script>#is', $rendered, $matches)) {
                $out .= implode('', $matches[0]);
            }
        }

        return $out;
    }

    /** The one URL, versioned by the two blades' own content so a deploy invalidates it. */
    public static function styleUrl(): string
    {
        $version = \Illuminate\Support\Facades\Cache::remember('adminops.style-version', 3600, function (): string {
            $stamp = '';

            foreach (['skin', 'styles'] as $part) {
                $file = __DIR__ . '/resources/views/' . $part . '.blade.php';
                $stamp .= is_file($file) ? filemtime($file) . ':' . filesize($file) . '|' : '';
            }

            return substr(md5($stamp), 0, 10);
        });

        return url('/admin/adminops-' . $version . '.css');
    }

    /**
     * Serves both style sheets as one immutable file.
     *
     * Public on purpose: it is CSS, it carries no data, and the panel's login page needs it
     * before anyone is authenticated. The version in the path is a content hash, so the
     * far-future cache header can never serve a stale skin after a deploy.
     */
    private function registerSkinStylesheet(): void
    {
        \Illuminate\Support\Facades\Route::get('/admin/adminops-{version}.css', function (string $version) {
            $css = \Illuminate\Support\Facades\Cache::remember('adminops.style-css', 3600, function (): string {
                $out = '';

                foreach (['skin', 'styles'] as $part) {
                    $rendered = Blade::render('@include(\'adminops::' . $part . '\')');

                    // Only what is inside <style>. The blades also carry <script> blocks,
                    // and taking the whole file swept those into the sheet — where they
                    // did nothing, having been removed from the page that needed them.
                    if (preg_match_all('#<style\b[^>]*>(.*?)</style>#is', $rendered, $matches)) {
                        $out .= implode("\n", $matches[1]) . "\n";
                    }
                }

                return trim($out);
            });

            return response($css, 200, [
                'Content-Type' => 'text/css; charset=UTF-8',
                'Cache-Control' => 'public, max-age=31536000, immutable',
                'ETag' => '"' . $version . '"',
            ]);
        })->where('version', '[a-z0-9]+')->name('adminops.styles');
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
