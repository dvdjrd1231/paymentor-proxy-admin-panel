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

        $this->registerNoFillDirective();
        $this->registerStyles();
        $this->registerWhmcsSkin();
        $this->keepSignInsRecorded();
        $this->keepTheDailyLogWritable();
        $this->registerQuotePdf();
        $this->registerUpdatesNotice();
        $this->sweepServiceOverrides();
        $this->retireRawProductList();
        $this->retireCoreCurrencyScreens();
        $this->retireCoreRoleScreens();
        $this->keepExtensionMigrationsApplied();
        $this->retireCoreExtensionBrowser();
        $this->retireCoreOauthScreens();
    }

    /**
     * Core's OAuth client resource, replaced by the reference's own OpenID Connect pair
     * (Leandro, 2026-09-07: "The OpenId Connect page should be updated as same as ... and
     * have create / edit page"). Named after the routes they displace, for the reason on
     * {@see retireRawProductList}.
     */
    private function retireCoreOauthScreens(): void
    {
        $guard = fn (callable $to) => function (...$arguments) use ($to) {
            if (!\Illuminate\Support\Facades\Auth::check()) {
                return redirect()->guest('/admin/login');
            }

            return redirect()->to($to(...$arguments));
        };

        \Illuminate\Support\Facades\Route::middleware(['web'])
            ->get('/admin/oauth-clients', $guard(fn () => Admin\Pages\OauthClients::getUrl()))
            ->name('filament.admin.resources.oauth-clients.index');

        \Illuminate\Support\Facades\Route::middleware(['web'])
            ->get('/admin/oauth-clients/create', $guard(fn () => Admin\Pages\OauthClient::getUrl()))
            ->name('filament.admin.resources.oauth-clients.create');

        \Illuminate\Support\Facades\Route::middleware(['web'])
            ->get('/admin/oauth-clients/{record}/edit', $guard(
                fn (string $record) => Admin\Pages\OauthClient::getUrl(['record' => $record]),
            ))->name('filament.admin.resources.oauth-clients.edit');
    }

    /**
     * Core's Available Extensions screen could not be brought to the house standard with
     * CSS: its heading is the raw class name — "Extension" — and it draws a second sidebar
     * inside the content, repeating what the left rail already carries. Both are markup.
     * {@see Admin\Pages\AvailableExtensions} is that screen rebuilt, and core's URL, with
     * its `?tab=` intact, lands there.
     */
    private function retireCoreExtensionBrowser(): void
    {
        // Named after the route it displaces — see the note on {@see retireRawProductList}.
        \Illuminate\Support\Facades\Route::middleware(['web'])->get('/admin/extensions/extension', function () {
            if (!\Illuminate\Support\Facades\Auth::check()) {
                return redirect()->guest('/admin/login');
            }

            // core's ?tab=installable is this page's own tab name too, so the query
            // string carries over untouched and a bookmarked tab still opens.
            return redirect()->to(Admin\Pages\AvailableExtensions::getUrl(
                array_filter(['tab' => request()->query('tab')]),
            ));
        })->name('filament.admin.extensions.pages.extension');
    }

    /**
     * Enabling an extension from the admin area applies its migrations (Leandro,
     * 2026-09-07: "find the solution to fix - install / uninstall extensions").
     *
     * Core's enable path — `EditExtension::handleRecordUpdate`, and our own
     * {@see Admin\Pages\ExtensionsList} which mirrors it — calls only the `enabled()`
     * hook. Every one of this deployment's seventeen extensions puts its setup in
     * `installed()` instead, which is the hook the CLI installer calls; exactly one
     * extension in the tree implements `enabled()`, and it is vendored Stripe. So
     * enabling an extension whose migrations had never run turned it on with its tables
     * missing, and the first page that touched one 500'd. That is the install half of his
     * report, and `docs/CORE-TOUCHPOINTS.md` had it recorded the other way round.
     *
     * `installed()` runs `ExtensionHelper::runMigrations`, which is Laravel's migrator: it
     * skips what the `migrations` table already records, so calling it on every enable is
     * a no-op for an extension that is already set up.
     *
     * **Disable stays non-destructive on purpose.** `uninstalled()` rolls the migrations
     * *back* — it drops the extension's tables and everything in them — so it must stay
     * where core put it, on the explicit Uninstall action whose own dialog warns that it
     * removes all data. Turning an extension off for an afternoon must not cost its data.
     */
    private function keepExtensionMigrationsApplied(): void
    {
        $apply = function (\App\Models\Extension $extension): void {
            try {
                ExtensionHelper::call($extension, 'installed', mayFail: true);
            } catch (\Throwable $exception) {
                // A failed migration must not also break the save that triggered it: the
                // admin has just turned something on and needs to be told, not 500'd.
                \Illuminate\Support\Facades\Log::error('AdminOps: could not apply extension migrations', [
                    'extension' => $extension->extension,
                    'exception' => $exception->getMessage(),
                ]);
            }
        };

        \App\Models\Extension::created(function (\App\Models\Extension $extension) use ($apply): void {
            if ($extension->enabled) {
                $apply($extension);
            }
        });

        \App\Models\Extension::updated(function (\App\Models\Extension $extension) use ($apply): void {
            // Only the moment it turns on — not every settings save on an extension that
            // was already enabled.
            if ($extension->enabled && $extension->wasChanged('enabled')) {
                $apply($extension);
            }
        });
    }

    /**
     * Leandro, 2026-09-07: "Why there are two pages and there should be only one page".
     * Core's Roles resource and our Administrator Roles screen were both reachable, and
     * only ours carries the reference's design — so `/admin/roles` and its create and
     * edit URLs all land on ours now. One screen, reachable from either address.
     *
     * `/admin/roles/1/edit` was the "error" in his second point: it answers 403 because
     * {@see \App\Admin\Resources\RoleResource::canEdit} refuses the full-administrator
     * group outright. {@see Admin\Pages\RoleGroup} allows it, with a guard on the one
     * edit that cannot be undone — see that class.
     */
    private function retireCoreRoleScreens(): void
    {
        $guarded = function (callable $to) {
            return function (...$arguments) use ($to) {
                if (!\Illuminate\Support\Facades\Auth::check()) {
                    return redirect()->guest('/admin/login');
                }

                return redirect()->to($to(...$arguments));
            };
        };

        \Illuminate\Support\Facades\Route::middleware(['web'])
            ->get('/admin/roles', $guarded(fn () => Admin\Pages\AdminRoles::getUrl()))
            ->name('filament.admin.resources.roles.index');

        // Registered before the `{record}` route so "create" is never read as an id.
        \Illuminate\Support\Facades\Route::middleware(['web'])
            ->get('/admin/roles/create', $guarded(fn () => Admin\Pages\RoleGroup::getUrl()))
            ->name('filament.admin.resources.roles.create');

        \Illuminate\Support\Facades\Route::middleware(['web'])
            ->get('/admin/roles/{record}/edit', $guarded(
                fn (string $record) => Admin\Pages\RoleGroup::getUrl(['record' => $record]),
            ))->name('filament.admin.resources.roles.edit');
    }

    /**
     * Core's Currencies list and its edit form are the third pair of duplicate doors, and
     * the edit form is the one that matters: it has no Base Conv. Rate, so a currency
     * saved through it silently keeps whatever rate it had (Leandro, 2026-09-07:
     * "these pages don't have 'Base Conv, Rate' Field. it is basic foundation").
     *
     * {@see Admin\Pages\EditCurrency} carries every field core's form had plus the rate,
     * so nothing is lost by sending both URLs there. Same shape as the Products and
     * Gateways redirects above, including the signed-out guard.
     */
    private function retireCoreCurrencyScreens(): void
    {
        \Illuminate\Support\Facades\Route::middleware(['web'])->get('/admin/currencies', function () {
            if (!\Illuminate\Support\Facades\Auth::check()) {
                return redirect()->guest('/admin/login');
            }

            return redirect()->to(Admin\Pages\CurrenciesList::getUrl());
        })->name('filament.admin.resources.currencies.index');

        \Illuminate\Support\Facades\Route::middleware(['web'])->get('/admin/currencies/{record}/edit', function (string $record) {
            if (!\Illuminate\Support\Facades\Auth::check()) {
                return redirect()->guest('/admin/login');
            }

            return redirect()->to(Admin\Pages\EditCurrency::getUrl(['record' => $record]));
        })->name('filament.admin.resources.currencies.edit');
    }

    /**
     * Core's raw Products list is retired in favour of the catalogue (Leandro,
     * 2026-09-06: "remove this page"). It has been out of the menus since issue #35 —
     * he still reached it through the product editor's own "Products" breadcrumb, which
     * this same redirect now lands on the catalogue instead, closing his second point
     * in one move.
     *
     * A redirect rather than a 404: the URL is still what core's breadcrumb and any
     * bookmark point at, and sending someone to the screen that replaced it beats a
     * dead end. Registered before Filament's own panel routes so it wins the match;
     * the guard keeps it from firing for a signed-out visitor, who should meet the
     * login page as usual.
     */
    /**
     * **Every redirect below carries the route name it displaces.** Registering a route at
     * a URI Filament also registers takes that URI's slot in the route collection, and the
     * Filament route's *name* goes with it — `filament.admin.resources.gateways.index` and
     * six others simply stopped existing. Nothing notices until a core page renders a link
     * to one, and then it is a 500: Leandro hit exactly that on 2026-09-07, editing a
     * gateway from Payment Gateways, whose breadcrumb links to the gateways index.
     *
     * Naming our redirect after the route it replaced puts the name back, pointing at the
     * redirect — so every internal link resolves again and lands on the screen that
     * replaced the old one, which is what the link meant in the first place.
     */
    private function retireRawProductList(): void
    {
        \Illuminate\Support\Facades\Route::middleware(['web'])->get('/admin/products', function () {
            if (!\Illuminate\Support\Facades\Auth::check()) {
                return redirect()->guest('/admin/login');
            }

            return redirect()->to(Admin\Pages\Catalogue::getUrl());
        })->name('filament.admin.resources.products.index');

        // Same story for core's raw Gateways list: Payment Gateways is the screen with
        // the reference's Enable/Disable/Edit rows, and two doors onto one feature is
        // what Leandro flagged on 2026-09-07. The edit form behind it stays reachable —
        // Payment Gateways' own Edit is what links to it.
        \Illuminate\Support\Facades\Route::middleware(['web'])->get('/admin/gateways', function () {
            if (!\Illuminate\Support\Facades\Auth::check()) {
                return redirect()->guest('/admin/login');
            }

            return redirect()->to(Admin\Pages\PaymentGateways::getUrl());
        })->name('filament.admin.resources.gateways.index');
    }

    /**
     * The Client Profile's Termination Date and Override Auto-Suspend fields, enforced —
     * see {@see Support\ServiceOverrides}. Hourly, with the exact guard the Cancellations
     * extension documents: a throw while *registering* scheduled work runs inside
     * `booted()` on every request and would 500 the whole site, so it is caught and
     * logged instead.
     */
    private function sweepServiceOverrides(): void
    {
        app()->booted(function (): void {
            try {
                app(\Illuminate\Console\Scheduling\Schedule::class)
                    ->call(fn () => Support\ServiceOverrides::sweep())
                    ->hourly()
                    ->name('adminops-service-overrides')
                    ->withoutOverlapping()
                    ->onOneServer();
            } catch (\Throwable $exception) {
                \Illuminate\Support\Facades\Log::error('AdminOps: could not register the service-overrides sweep', [
                    'exception' => $exception->getMessage(),
                ]);
            }
        });
    }

    /**
     * `@nofill` — keep browsers and password managers out of a field.
     *
     * A search band reading Name / Email / Phone is the exact shape Chrome's address autofill
     * and every password manager scan for, and each answers by planting its own icon in the
     * first field. LastPass's is a red square, and on the one machine running it the band came
     * out short a hundred pixels while every other machine showed it correctly.
     *
     * These attributes have to be in the markup, not stamped on afterwards: an extension reads
     * the field as soon as it appears, so anything applied from a DOMContentLoaded handler is
     * racing something that has usually already decided. A directive rather than five
     * attributes repeated across eleven blades — the reason lives here, once.
     */
    private function registerNoFillDirective(): void
    {
        Blade::directive('nofill', fn (): string => "<?php echo 'autocomplete=\"off\" "
            . 'data-lpignore="true" '        // LastPass
            . 'data-1p-ignore="true" '       // 1Password
            . 'data-bwignore="true" '        // Bitwarden
            . "data-form-type=\"other\"'; ?>");
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
     * Issue #27 — core's own Utilities → Updates page, unedited (a core touchpoint we cannot
     * remove), reads Your Version as "development": this deployment has no tagged release,
     * it is a git checkout that moves with every commit, which is exactly why "development"
     * is the honest label rather than a bug. What actually IS wrong is core's "Update"
     * button sitting right there regardless — it runs `artisan app:upgrade`, core's own
     * self-updater, which downloads and applies an official Paymenter release package. That
     * assumes an install core manages entirely; this one is git-deployed, Docker-based, and
     * carries our own extension layer on top. Letting that updater run for real risks
     * overwriting files our git history and deploy scripts do not expect touched, on a store
     * that is live.
     *
     * Not core's page to be told apart from a real one — core has no signal to give it. The
     * banner is added from here, scoped to this one route so it never leaks onto a page it
     * was not written for, and the two buttons are guarded by a confirm() that says what
     * they would actually try to do before either is allowed to fire.
     */
    private function registerUpdatesNotice(): void
    {
        FilamentView::registerRenderHook('panels::page.header.heading.after', function (): string {
            if (!request()->is('admin/updates')) {
                return '';
            }

            return <<<'HTML'
                <div class="ao-upd-notice">
                    <p>
                        <strong>"development" is correct, not broken.</strong> This store runs from a
                        git checkout that is redeployed on every commit, not a tagged release core can
                        version — there is no release number for it to show.
                    </p>
                    <p>
                        <strong>Do not use Check for Updates or Update below.</strong> Both run core's
                        own self-updater, which downloads and installs an official Paymenter package —
                        something this deployment's Docker setup, extensions, and git history are not
                        built to survive. To update this store, have it redeployed from git instead.
                    </p>
                </div>
                <script>
                    (() => {
                        const warn = (label, verb) => {
                            const btn = [...document.querySelectorAll('button, a')]
                                .find((el) => el.textContent.trim() === label);
                            if (!btn || btn.dataset.aoWarned) return;
                            btn.dataset.aoWarned = '1';
                            btn.addEventListener('click', (event) => {
                                if (btn.dataset.aoConfirmed) { delete btn.dataset.aoConfirmed; return; }
                                event.stopImmediatePropagation();
                                event.preventDefault();
                                if (confirm(
                                    'This runs core\'s own updater, which tries to install an official '
                                    + 'Paymenter package over this git-deployed, Docker-based store — not '
                                    + 'how this install is updated. ' + verb + ' anyway?'
                                )) {
                                    btn.dataset.aoConfirmed = '1';
                                    btn.click();
                                }
                            }, true);
                        };

                        const run = () => { warn('Check for updates', 'Check'); warn('Update', 'Update'); };
                        run();
                        document.addEventListener('livewire:navigated', run);
                        document.addEventListener('livewire:init', () => window.Livewire?.hook?.('morphed', run));
                    })();
                </script>
                HTML;
        });
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

    /**
     * The two blades' own content, as a short hash.
     *
     * Hashed from the bytes, not from mtime and size. The URL this feeds is served
     * `immutable, max-age=1y` and sits behind Cloudflare, so the hash has one job: never
     * name two different stylesheets the same. mtime+size did that job badly in both
     * directions — a `git pull` that rewrote a blade byte-for-byte minted a pointless new
     * URL, and any edit that happened to preserve the size would have reused an old one.
     * It also meant a deploy had to remember to `touch` the blades, a ritual that is now
     * gone.
     */
    public static function styleVersion(): string
    {
        return \Illuminate\Support\Facades\Cache::remember('adminops.style-version', 3600, function (): string {
            $stamp = '';

            foreach (['skin', 'styles'] as $part) {
                $file = __DIR__ . '/resources/views/' . $part . '.blade.php';
                $stamp .= is_file($file) ? md5_file($file) . '|' : '';
            }

            return substr(md5($stamp), 0, 10);
        });
    }

    /** The one URL, versioned by the two blades' own content so a deploy invalidates it. */
    public static function styleUrl(): string
    {
        return url('/admin/adminops-' . static::styleVersion() . '.css');
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
            // Keyed by the same content hash the URL carries, so new CSS gets a new key
            // and the old entry is simply never read again. It used to be a bare
            // 'adminops.style-css', which meant a deploy had to remember to forget two
            // keys — miss the second and the new URL served the old stylesheet for an
            // hour, which is exactly what happened on 2026-09-07.
            $css = \Illuminate\Support\Facades\Cache::remember('adminops.style-css.' . static::styleVersion(), 3600, function (): string {
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

        // Issue #10's "add flag before the country name": Windows has no flag glyphs, so
        // regional-indicator emoji fall back to two-letter codes — inside native <select>
        // options, where no markup can help. This font (Twemoji Country Flags, MPL-2.0,
        // flags CC-BY 4.0) covers only the flag codepoints; prepended to the font stack it
        // fills exactly that gap and touches nothing else. Vendored, not a CDN.
        \Illuminate\Support\Facades\Route::get('/admin/adminops-flags.woff2', function () {
            $file = __DIR__ . '/resources/fonts/TwemojiCountryFlags.woff2';

            abort_unless(is_file($file), 404);

            return response()->file($file, [
                'Content-Type' => 'font/woff2',
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ]);
        })->name('adminops.flags-font');
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
