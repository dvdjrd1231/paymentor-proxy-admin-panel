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
        $this->refuseClosedAccounts();
        $this->retireRawProductList();
        $this->retireCoreCurrencyScreens();
        $this->retireCoreRoleScreens();
        $this->keepExtensionMigrationsApplied();
        $this->retireCoreExtensionBrowser();
        $this->retireCoreOauthScreens();
        $this->retireCoreGatewayEditor();
        $this->retireCoreProductEditor();
        $this->retireCoreServerEditor();
        $this->retireCoreUserSubPages();

        $this->retireRawCoreScreens();

        $this->registerTicketPrintView();
        $this->registerAccountMenuItems();

        $this->countProductUrlVisits();
        $this->attachTemplateFiles();
        $this->registerErrorPages();
        $this->applyClientGroupDiscounts();
        $this->creditCancelledServices();
    }

    /**
     * Branding on the account menu (Leandro, 2026-09-11: "Logo / Dark logo template /
     * Favicon … I think these are existed in User Icon Menu Item on top right menu").
     * Core builds that menu in its panel provider, which we do not edit; a panel takes
     * further items at serve time and merges them with its own.
     */
    private function registerAccountMenuItems(): void
    {
        \Filament\Facades\Filament::serving(function (): void {
            $panel = \Filament\Facades\Filament::getPanel('admin', isStrict: false);

            if (!$panel || !Admin\Pages\Branding::canAccess()) {
                return;
            }

            $panel->userMenuItems([
                'branding' => \Filament\Actions\Action::make('branding')
                    ->label('Branding')
                    ->icon('heroicon-o-photo')
                    ->url(fn (): string => Admin\Pages\Branding::getUrl())
                    // Above Exit Admin and Sign out: the menu appends, and the reference
                    // keeps leaving the admin and logging out at the foot of its own.
                    ->sort(-10),
            ]);
        });
    }

    /** Credit the unused period back when a service is cancelled (Leandro, 2026-09-08). */
    private function creditCancelledServices(): void
    {
        \App\Models\Service::updated(
            fn (\App\Models\Service $service) => Support\CancellationCredit::handle($service),
        );
    }

    /**
     * Client-group discounts, applied for real (Leandro, 2026-09-07: "these group will
     * this condition as common. this is client group").
     */
    private function applyClientGroupDiscounts(): void
    {
        Event::listen(\App\Events\InvoiceItem\Created::class, function ($event): void {
            $item = $event->invoiceItem;

            if ($item->reference_type === Support\ClientGroup::MARKER) {
                return;
            }

            try {
                if ($invoice = $item->invoice) {
                    Support\ClientGroup::applyDiscount($invoice);
                }
            } catch (\Throwable $exception) {
                // Billing must not fail because a discount could not be worked out; the
                // invoice stands at full price and the reason is in the log.
                \Illuminate\Support\Facades\Log::error('AdminOps: could not apply the client group discount', [
                    'invoice' => $item->invoice_id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        });
    }

    /**
     * One error page for the whole platform (Leandro, 2026-09-07: "Every Admin Error page
     * should be updated as WHMCS page standard format. Also, every client error page
     * should have correct alignment").
     */
    private function registerErrorPages(): void
    {
        View::getFinder()->prependLocation(__DIR__ . '/resources/error-views');
    }

    /**
     * Core's product editor, replaced by the reference's tabbed Edit Product screen
     * (Leandro, 2026-09-08, screenshots of `configproducts.php?action=edit`).
     */
    private function retireCoreProductEditor(): void
    {
        \Illuminate\Support\Facades\Route::middleware(['web'])
            ->get('/admin/products/{record}/edit', function (string $record) {
                if (!\Illuminate\Support\Facades\Auth::check()) {
                    return redirect()->guest('/admin/login');
                }

                return redirect()->to(Admin\Pages\EditProduct::getUrl(['record' => $record]));
            })->name('filament.admin.resources.products.edit');
    }

    /**
     * Core's per-client sub-pages, replaced by the client profile's own tabs (Leandro,
     * 2026-09-09: "/admin/users/3/invoices should not be existed").
     */
    private function retireCoreUserSubPages(): void
    {
        $tabs = [
            'services' => 'services',
            'invoices' => 'invoices',
            'tickets' => 'tickets',
            // The reference reaches credits through the summary's Manage Credits, not a tab.
            'credits' => 'summary',
        ];

        foreach ($tabs as $segment => $tab) {
            \Illuminate\Support\Facades\Route::middleware(['web'])
                ->get('/admin/users/{record}/' . $segment, function (string $record) use ($tab) {
                    if (!\Illuminate\Support\Facades\Auth::check()) {
                        return redirect()->guest('/admin/login');
                    }

                    return redirect()->to(Admin\Pages\ClientSummary::getUrl([
                        'record' => $record,
                        'tab' => $tab,
                    ]));
                })->name('filament.admin.resources.users.' . $segment);
        }
    }

    /**
     * Core's server create/edit form, replaced by the reference's own (Leandro, 2026-09-08
     * sent the WHMCS Add Server screens with "Servers — list and edit. This is where
     * provisioning is configured.").
     */
    private function retireCoreServerEditor(): void
    {
        \Illuminate\Support\Facades\Route::middleware(['web'])
            ->get('/admin/servers/create', function () {
                if (!\Illuminate\Support\Facades\Auth::check()) {
                    return redirect()->guest('/admin/login');
                }

                return redirect()->to(Admin\Pages\EditServer::getUrl());
            })->name('filament.admin.resources.servers.create');

        \Illuminate\Support\Facades\Route::middleware(['web'])
            ->get('/admin/servers/{record}/edit', function (string $record) {
                if (!\Illuminate\Support\Facades\Auth::check()) {
                    return redirect()->guest('/admin/login');
                }

                return redirect()->to(Admin\Pages\EditServer::getUrl(['record' => $record]));
            })->name('filament.admin.resources.servers.edit');
    }

    /**
     * Core's gateway editor, replaced by the reference's own (Leandro, 2026-09-07: it
     * "is working as correctly but page design and styles format should be the WHMCS
     * page standard format").
     */
    private function retireCoreGatewayEditor(): void
    {
        \Illuminate\Support\Facades\Route::middleware(['web'])
            ->get('/admin/gateways/{record}/edit', function (string $record) {
                if (!\Illuminate\Support\Facades\Auth::check()) {
                    return redirect()->guest('/admin/login');
                }

                return redirect()->to(Admin\Pages\EditGateway::getUrl(['record' => $record]));
            })->name('filament.admin.resources.gateways.edit');
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
     */
    /**
     * **Every redirect below carries the route name it displaces.** Registering a route at
     * a URI Filament also registers takes that URI's slot in the route collection, and the
     * Filament route's *name* goes with it — `filament.admin.resources.gateways.index` and
     * six others simply stopped existing. Nothing notices until a core page renders a link
     * to one, and then it is a 500: Leandro hit exactly that on 2026-09-07, editing a
     * gateway from Payment Gateways, whose breadcrumb links to the gateways index.
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
     * Core's remaining raw resource screens, replaced by their window-standard pages
     * (Leandro, 2026-09-11). Same rule as above: each redirect carries the route name it
     * displaces, or core's own getUrl() calls to it 500.
     */
    private function retireRawCoreScreens(): void
    {
        $guarded = function (callable $target): \Closure {
            return function (...$args) use ($target) {
                if (!\Illuminate\Support\Facades\Auth::check()) {
                    return redirect()->guest('/admin/login');
                }

                return redirect()->to($target(...$args));
            };
        };

        $routes = [
            '/admin/users' => ['filament.admin.resources.users.index', fn (): string => Admin\Pages\Administrators::getUrl()],
            '/admin/failed-jobs' => ['filament.admin.resources.failed-jobs.index', fn (): string => Admin\Pages\FailedJobs::getUrl()],
            '/admin/error-logs' => ['filament.admin.resources.error-logs.index', fn (): string => Admin\Pages\ErrorLog::getUrl()],
            '/admin/notification-templates' => ['filament.admin.resources.notification-templates.index', fn (): string => Admin\Pages\EmailTemplates::getUrl()],
            '/admin/notification-templates/create' => ['filament.admin.resources.notification-templates.create', fn (): string => Admin\Pages\EmailTemplates::getUrl()],
        ];

        foreach ($routes as $uri => [$name, $target]) {
            \Illuminate\Support\Facades\Route::middleware(['web'])->get($uri, $guarded($target))->name($name);
        }

        \Illuminate\Support\Facades\Route::middleware(['web'])
            ->get('/admin/notification-templates/{record}/edit', $guarded(
                fn (string $record): string => Admin\Pages\EditEmailTemplate::getUrl(['record' => $record]),
            ))->name('filament.admin.resources.notification-templates.edit');
    }

    /**
     * The Visits figure on Edit Product's Links tab. The reference counts hits per product
     * URL; this counts them the same way, against the path the visitor actually used, so a
     * product reached by two addresses shows two separate figures.
     */
    private function countProductUrlVisits(): void
    {
        // RouteMatched, with the slug resolved here rather than read off the route: this
        // fires before substituteBindings, so the product parameter is still a string at
        // this point — taking it as a model is why the first attempt counted nothing.
        Event::listen(\Illuminate\Routing\Events\RouteMatched::class, function ($event): void {
            try {
                if ($event->route->getName() !== 'products.show' || !$event->request->isMethod('GET')) {
                    return;
                }

                $slug = $event->route->parameter('product');
                $slug = $slug instanceof \App\Models\Product ? $slug->slug : (string) $slug;

                $productId = \App\Models\Product::where('slug', $slug)->value('id');

                if (!$productId) {
                    return;
                }

                \Illuminate\Support\Facades\DB::table('ext_product_url_visits')->upsert(
                    [[
                        'product_id' => $productId,
                        'path' => substr('/' . ltrim($event->request->path(), '/'), 0, 191),
                        'visits' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]],
                    ['product_id', 'path'],
                    ['visits' => \Illuminate\Support\Facades\DB::raw('visits + 1'), 'updated_at' => now()],
                );
            } catch (\Throwable $e) {
                // A counter must never take the storefront down.
                report($e);
            }
        });
    }

    /** The reference's per-template Attachments, put on the message as it goes out. */
    private static bool $mailListenerRegistered = false;

    private function attachTemplateFiles(): void
    {
        // boot() runs more than once in a process — the extension list is walked again on
        // each bootstrap — and a listener registered twice attaches every file twice. The
        // test sent one attachment and got three before this guard.
        if (static::$mailListenerRegistered) {
            return;
        }

        static::$mailListenerRegistered = true;

        Event::listen(\Illuminate\Mail\Events\MessageSending::class, function ($event): bool {
            try {
                $template = $event->data['emailTemplate'] ?? null;

                if (!$template instanceof \App\Models\NotificationTemplate) {
                    return true;
                }

                $files = Models\EmailTemplateAttachment::where('template_id', $template->id)->get();

                foreach ($files as $file) {
                    $path = $file->absolutePath();

                    // A file removed from disk behind our back must not stop the email.
                    if (!is_file($path)) {
                        report(new \RuntimeException('Email template attachment missing: ' . $path));

                        continue;
                    }

                    $event->message->attachFromPath($path, $file->filename, $file->mime_type ?: null);
                }
            } catch (\Throwable $e) {
                report($e);
            }

            return true;
        });
    }

    /**
     * The reference's View Printable Version — a standalone page, not window.print() on the
     * editor, which printed the whole admin chrome and every form on it.
     */
    private function registerTicketPrintView(): void
    {
        \Illuminate\Support\Facades\Route::middleware(['web', 'auth'])
            ->get('/admin/ticket/{ticket}/print', function (\App\Models\Ticket $ticket) {
                abort_unless(\App\Admin\Resources\TicketResource::canViewAny(), 403);

                $messages = $ticket->messages()->with(['user', 'attachments'])->oldest()->get();
                $requestor = $messages->first()?->user ?? $ticket->user;
                $name = fn ($user): string => $user
                    ? (trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->email)
                    : 'Unknown';

                return view('adminops::pages.ticket-print', [
                    'ticket' => $ticket,
                    'messages' => $messages,
                    'lastReply' => $messages->last(),
                    'ownerName' => $name($ticket->user),
                    'requestorName' => $name($requestor),
                    'requestorIsStaff' => (bool) $requestor?->role_id,
                ]);
            })->name('adminops.ticket.print');
    }

    /**
     * The Client Profile's Termination Date and Override Auto-Suspend fields, enforced —
     * see {@see Support\ServiceOverrides}. Hourly, with the exact guard the Cancellations
     * extension documents: a throw while *registering* scheduled work runs inside
     * `booted()` on every request and would 500 the whole site, so it is caught and
     * logged instead.
     */
    /**
     * A closed account cannot sign in — the half of the reference's Close Client Account
     * that has to live outside the button.
     */
    private function refuseClosedAccounts(): void
    {
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Login::class, function ($event): void {
            $user = $event->user ?? null;

            if (!$user instanceof \App\Models\User || $user->role_id !== null) {
                return;
            }

            try {
                $closed = Models\Meta::for($user)['closed_at'] ?? null;
            } catch (\Throwable) {
                return;     // before the meta table exists, nothing is closed
            }

            if ($closed === null) {
                return;
            }

            \Illuminate\Support\Facades\Auth::guard($event->guard ?? 'web')->logout();
            session()->invalidate();
            session()->flash('error', 'This account has been closed. Please contact support.');
        });
    }

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

    /** `@nofill` — keep browsers and password managers out of a field. */
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
    /** The panel is light, whatever the browser prefers. */
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

    /** The two blades' own content, as a short hash. */
    public static function styleVersion(): string
    {
        $stamp = '';

        foreach (['skin', 'styles'] as $part) {
            $file = __DIR__ . '/resources/views/' . $part . '.blade.php';
            $stamp .= is_file($file) ? md5_file($file) . '|' : '';
        }

        return substr(md5($stamp), 0, 10);
    }

    /** The one URL, versioned by the two blades' own content so a deploy invalidates it. */
    public static function styleUrl(): string
    {
        return url('/admin/adminops-' . static::styleVersion() . '.css');
    }

    /** Serves both style sheets as one immutable file. */
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
