<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

use App\Admin\Pages\CronStats;
use App\Admin\Pages\Settings;
use App\Admin\Pages\Updates;
use App\Admin\Resources\ApiResource;
use App\Admin\Resources\Audits\AuditResource;
use App\Admin\Resources\CategoryResource;
use App\Admin\Resources\ConfigOptionResource;
use App\Admin\Resources\CouponResource;
use App\Admin\Resources\CurrencyResource;
use App\Admin\Resources\CustomPropertyResource;
use App\Admin\Resources\EmailLogResource;
use App\Admin\Resources\ErrorLogResource;
use App\Admin\Resources\ExtensionResource;
use App\Admin\Resources\FailedJobResource;
use App\Admin\Resources\GatewayResource;
use App\Admin\Resources\HttpLogResource;
use App\Admin\Resources\InvoiceResource;
use App\Admin\Resources\InvoiceTransactions\InvoiceTransactionResource;
use App\Admin\Resources\NotificationTemplateResource;
use App\Admin\Resources\OauthClientResource;
use App\Admin\Resources\OrderResource;
use App\Admin\Resources\ProductResource;
use App\Admin\Resources\RoleResource;
use App\Admin\Resources\ServerResource;
use App\Admin\Resources\ServiceCancellationResource;
use App\Admin\Resources\ServiceResource;
use App\Admin\Resources\TaxRateResource;
use App\Admin\Resources\TicketResource;
use App\Admin\Resources\UserResource;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\AddNewClient;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\AutomationStatus;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\Catalogue;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\ManageAffiliates;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\ManageUsers;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\ProductsServices;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\ViewSearchClients;
use Paymenter\Extensions\Others\Announcements\Admin\Resources\AnnouncementResource;
use Paymenter\Extensions\Others\BillableItems\Admin\Resources\BillableItemResource;
use Paymenter\Extensions\Others\Cancellations\Admin\Resources\CancellationRequestResource;
use Paymenter\Extensions\Others\GatewayRules\Admin\Resources\GatewayRuleResource;
use Paymenter\Extensions\Others\InvoiceOps\Admin\Pages\Transactions;
use Paymenter\Extensions\Others\InvoiceOps\Admin\Resources\InvoiceOpsResource;
use Paymenter\Extensions\Others\InvoiceOps\Admin\Resources\RefundRequestResource;
use Paymenter\Extensions\Others\InvoiceOps\Admin\Resources\RefundResource;
use Paymenter\Extensions\Others\Knowledgebase\Admin\Resources\KbArticleResource;
use Paymenter\Extensions\Others\Knowledgebase\Admin\Resources\KbCategoryResource;
use Paymenter\Extensions\Others\PaymentFees\Admin\Resources\PaymentFeeRuleResource;
use Paymenter\Extensions\Others\ProvisioningOps\Admin\Resources\ProvisioningOperationResource;
use Paymenter\Extensions\Others\Quotes\Admin\Resources\QuoteResource;
use Paymenter\Extensions\Others\TermLimits\Admin\Resources\ProductTermResource;
use Paymenter\Extensions\Others\TermLimits\Admin\Resources\ServiceTermResource;
use Paymenter\Extensions\Others\TicketTools\Admin\Resources\CannedResponseResource;
use Paymenter\Extensions\Others\TicketTools\Admin\Resources\TicketNoteResource;
use Paymenter\Extensions\Servers\ProxyPanel\Admin\Pages\PanelLocations;

/**
 * The WHMCS menu bar, rebuilt over Paymenter's resources: grouped by what you are *doing*
 * (Clients, Orders, Billing…) rather than by record type as core groups them.
 *
 * Uses `Panel::navigation()` because a resource's group is a static property on the resource
 * class — regrouping the normal way would mean editing two dozen core files.
 *
 * Taking navigation over makes anything not named here unreachable, so {@see addons()} runs
 * last and sweeps up whatever was not placed. A new extension therefore still appears,
 * landing in Addons until someone files it properly.
 *
 * Every entry is permission-checked: a limited role sees a shorter menu, never a link that
 * answers 403.
 *
 * @link docs/02b-admin-area.md
 */
class WhmcsNavigation
{
    /**
     * Classes already given a home, so the Addons catch-all can tell what is left.
     *
     * @var array<class-string, true>
     */
    private static array $placed = [];

    /**
     * The built menus, memoised for the request.
     *
     * @var array<int, NavigationGroup>|null
     */
    private static ?array $groups = null;

    public static function build(NavigationBuilder $builder): NavigationBuilder
    {
        return $builder->groups(static::groups());
    }

    /**
     * Built once per request and shared with the rail, which shows the section you are in —
     * so the two cannot disagree about what exists, and the permission checks run once.
     *
     * @return array<int, NavigationGroup>
     */
    public static function groups(): array
    {
        if (static::$groups !== null) {
            return static::$groups;
        }

        // Reset per build, not per process: a stale array would leak between requests under
        // a persistent worker and silently empty the Addons menu.
        static::$placed = [];

        $groups = array_values(array_filter([
            static::clients(),
            static::orders(),
            static::billing(),
            static::support(),
            static::reports(),
            static::utilities(),
            // Built, then dropped below. See the note before the return.
            static::setup(),
            // Last, and only last: it can only work out the remainder once the rest have
            // claimed what they wanted.
            static::addons(),
        ]));

        // The reference has no Setup menu — its setup screens live behind the wrench in the
        // toolbar, which is where {@see Toolbar::utilities()} now puts ours. The group is
        // still *built* rather than simply deleted, because building it is what marks its
        // resources as placed; skip that and the Addons catch-all would sweep every setup
        // screen into one long dropdown.
        return static::$groups = array_values(array_filter(
            $groups,
            fn (NavigationGroup $group): bool => $group->getLabel() !== 'Setup',
        ));
    }

    /**
     * The group the current page belongs to, or null on the dashboard. Matched on path, not
     * Filament's active flag — entries report themselves inactive ({@see link()}) because
     * several point at one resource under different filters. Longest match wins.
     */
    public static function activeGroup(): ?NavigationGroup
    {
        $path = rtrim(parse_url(request()->url(), PHP_URL_PATH) ?? '', '/');
        $best = null;
        $bestLength = 0;

        foreach (static::groups() as $group) {
            foreach ($group->getItems() as $item) {
                $itemPath = rtrim(parse_url((string) $item->getUrl(), PHP_URL_PATH) ?? '', '/');

                if ($itemPath === '' || !str_starts_with($path, $itemPath)) {
                    continue;
                }

                if (strlen($itemPath) > $bestLength) {
                    $best = $group;
                    $bestLength = strlen($itemPath);
                }
            }
        }

        return $best;
    }

    private static function clients(): ?NavigationGroup
    {
        return static::group('Clients', 'ri-group-line', [
            // The reference's own screens, not core's list: Leandro's feedback screenshots
            // are of View/Search Clients and Manage Users, so those pages exist here and the
            // menu leads to them. Core's UserResource stays reachable through their Actions
            // column — it is still the only place a user is edited.
            static::page(ViewSearchClients::class, 'View/Search Clients'),
            static::page(AddNewClient::class, 'Add New Client'),
            static::page(ManageUsers::class, 'Manage Users'),
            static::page(ProductsServices::class, 'Products/Services'),
            // Ours, not core's: core's list offers Edit and Delete, and deleting a request is
            // indistinguishable from refusing it. Falls back to core's when the extension is
            // not installed, so the entry never disappears.
            class_exists(CancellationRequestResource::class)
                ? static::link(
                    CancellationRequestResource::class,
                    'Cancellation Requests',
                    badge: fn () => Metrics::cancellationsPending(),
                    badgeColor: 'danger',
                )
                : static::link(
                    ServiceCancellationResource::class,
                    'Cancellation Requests',
                    badge: fn () => Metrics::cancellationsPending(),
                    badgeColor: 'danger',
                ),
            static::page(ManageAffiliates::class, 'Manage Affiliates'),
        ]);
    }

    /**
     * Status filters point at *services*, not orders: a Paymenter order is a container row
     * with no status of its own. Same reasoning as {@see Metrics::newServices()}.
     */
    private static function orders(): ?NavigationGroup
    {
        return static::group('Orders', 'ri-shopping-bag-4-line', [
            static::link(OrderResource::class, 'List All Orders'),
            static::link(
                ServiceResource::class,
                'Pending Orders',
                params: ['filters' => ['status' => ['value' => 'pending']]],
                badge: fn () => Metrics::servicesPending(),
                badgeColor: 'info',
            ),
            static::link(
                ServiceResource::class,
                'Active Orders',
                params: ['filters' => ['status' => ['value' => 'active']]],
            ),
            // Beside the orders it constrains. The badge counts terms that are overdue and
            // still running, which should always be zero — a number there means the
            // every-minute scheduler is not running.
            static::link(
                ServiceTermResource::class,
                'Fixed Terms',
                badge: fn () => class_exists(ServiceTermResource::class)
                    ? ServiceTermResource::getNavigationBadge()
                    : null,
                badgeColor: 'danger',
            ),
            static::link(
                ServiceResource::class,
                'Suspended Orders',
                params: ['filters' => ['status' => ['value' => 'suspended']]],
                badge: fn () => Metrics::servicesSuspended(),
                badgeColor: 'warning',
            ),
            static::link(
                ServiceResource::class,
                'Cancelled Orders',
                params: ['filters' => ['status' => ['value' => 'cancelled']]],
            ),
            static::link(OrderResource::class, 'Add New Order', page: 'create'),
        ]);
    }

    private static function billing(): ?NavigationGroup
    {
        return static::group('Billing', 'ri-bill-line', [
            static::link(InvoiceTransactionResource::class, 'Transactions List'),
            // The reference's own Transactions page: Amount In, Fees, Amount Out, and the
            // three tiles above them. Core's list has Amount and nothing else, which cannot
            // answer what was actually kept.
            static::page(Transactions::class, 'Transactions Report'),
            static::link(
                RefundRequestResource::class,
                'Refund Requests',
                badge: fn () => class_exists(RefundRequestResource::class)
                    ? RefundRequestResource::getNavigationBadge()
                    : null,
                badgeColor: 'danger',
            ),
            static::link(RefundResource::class, 'Refunds'),
            static::link(InvoiceResource::class, 'All Invoices'),
            static::link(
                InvoiceResource::class,
                'Unpaid Invoices',
                params: ['filters' => ['status' => ['value' => 'pending']], 'sort' => 'due_at'],
                badge: fn () => Metrics::invoicesUnpaid(),
                badgeColor: fn () => Metrics::invoicesOverdue() ? 'danger' : 'gray',
            ),
            static::link(
                InvoiceResource::class,
                'Paid Invoices',
                params: ['filters' => ['status' => ['value' => 'paid']]],
            ),
            static::link(
                InvoiceResource::class,
                'Cancelled Invoices',
                params: ['filters' => ['status' => ['value' => 'cancelled']]],
            ),
            // The reference keeps its gateway log under Billing, and this is the nearest
            // thing Paymenter has: every outbound HTTP call, gateways included. Also in
            // Utilities, where core files it — two ways to one page, as with the invoice
            // filters above.
            // Publish a draft, record a refund, send one notice by hand — the three things
            // the reference's invoice page can do that core's cannot.
            static::link(
                QuoteResource::class,
                'Quotes',
                badge: fn () => class_exists(QuoteResource::class) ? QuoteResource::getNavigationBadge() : null,
                badgeColor: 'info',
            ),
            static::link(
                BillableItemResource::class,
                'Billable Items',
                badge: fn () => class_exists(BillableItemResource::class)
                    ? BillableItemResource::getNavigationBadge()
                    : null,
                badgeColor: 'warning',
            ),
            static::link(InvoiceOpsResource::class, 'Invoice Operations'),
            static::link(
                InvoiceResource::class,
                'Draft Invoices',
                params: ['filters' => ['status' => ['value' => 'draft']]],
            ),
            static::link(HttpLogResource::class, 'Gateway Log'),
            static::link(CouponResource::class, 'Coupons'),
            static::link(PaymentFeeRuleResource::class, 'Payment Fee Rules'),
            static::link(GatewayRuleResource::class, 'Gateway Rules'),
            static::link(TaxRateResource::class, 'Tax Rates'),
        ]);
    }

    private static function support(): ?NavigationGroup
    {
        return static::group('Support', 'ri-customer-service-line', [
            static::link(
                TicketResource::class,
                'Tickets Awaiting Reply',
                params: ['tab' => 'open'],
                badge: fn () => Metrics::ticketsAwaitingReply(),
                badgeColor: 'warning',
            ),
            static::link(TicketResource::class, 'All Tickets'),
            static::link(CannedResponseResource::class, 'Canned Responses'),
            static::link(TicketNoteResource::class, 'Internal Notes'),
            static::link(KbArticleResource::class, 'Knowledgebase Articles'),
            static::link(KbCategoryResource::class, 'Knowledgebase Categories'),
            static::link(AnnouncementResource::class, 'Announcements'),
        ]);
    }

    /**
     * Paymenter has no reporting module, so each entry is the pre-filtered list a report would
     * have been a view of. Staff look for this menu by name; filtered lists beat no menu.
     */
    private static function reports(): ?NavigationGroup
    {
        return static::group('Reports', 'ri-line-chart-line', [
            static::link(InvoiceTransactionResource::class, 'Income (Transactions)'),
            static::link(
                InvoiceResource::class,
                'Paid Invoices',
                params: ['filters' => ['status' => ['value' => 'paid']]],
            ),
            static::link(
                InvoiceResource::class,
                'Overdue Invoices',
                params: ['filters' => ['status' => ['value' => 'pending']], 'sort' => 'due_at'],
                badge: fn () => Metrics::invoicesOverdue(),
                badgeColor: 'danger',
            ),
            static::link(UserResource::class, 'New Customers', params: ['sort' => '-created_at']),
            static::link(
                ServiceResource::class,
                'Active Services',
                params: ['filters' => ['status' => ['value' => 'active']]],
            ),
        ]);
    }

    /** Logs, the job queue, the cron and the updater — where the reference keeps them. */
    private static function utilities(): ?NavigationGroup
    {
        return static::group('Utilities', 'ri-tools-line', [
            static::page(Updates::class, 'Update Paymenter'),
            // Administrator accounts, kept apart from the Clients menu on purpose: Manage
            // Users lists client logins only, as the reference does, and staff are managed
            // here — core's Users list is where roles are assigned.
            static::link(UserResource::class, 'Administrators'),
            class_exists(AutomationStatus::class)
                ? static::page(AutomationStatus::class, 'Automation Status')
                : static::page(CronStats::class, 'Automation Status'),
            static::page(CronStats::class, 'Cron Statistics'),
            static::link(
                ProvisioningOperationResource::class,
                'Provisioning Operations',
                badge: fn () => Metrics::provisioningFailures(),
                badgeColor: 'danger',
            ),
            static::link(FailedJobResource::class, 'Failed Jobs'),
            static::link(EmailLogResource::class, 'Email Log'),
            static::link(AuditResource::class, 'Audit Log'),
            static::link(ErrorLogResource::class, 'Error Log'),
            static::link(HttpLogResource::class, 'HTTP Log'),
        ]);
    }

    /** WHMCS's Setup menu — the spanner in its top-right corner. */
    private static function setup(): ?NavigationGroup
    {
        return static::group('Setup', 'ri-settings-3-line', [
            // First, as it is on the reference: the catalogue as a whole, ordered by
            // dragging. The two resource lists below it are where a single record is
            // created, edited and deleted.
            static::page(Catalogue::class, 'Products/Services'),
            static::link(ProductResource::class, 'Products'),
            static::link(CategoryResource::class, 'Categories'),
            // The reference's Auto Terminate/Fixed Term field, which lives on the product's
            // Pricing tab there and cannot here — a resource's form is no more extensible
            // from an extension than its table is.
            static::link(ProductTermResource::class, 'Auto Terminate'),
            static::link(ConfigOptionResource::class, 'Configurable Options'),
            static::link(ServerResource::class, 'Servers'),
            static::page(PanelLocations::class, 'Panel Locations'),
            static::link(GatewayResource::class, 'Payment Gateways'),
            static::link(CurrencyResource::class, 'Currencies'),
            static::link(CustomPropertyResource::class, 'Custom Properties'),
            static::link(NotificationTemplateResource::class, 'Email Templates'),
            static::link(RoleResource::class, 'Administrator Roles'),
            static::link(ApiResource::class, 'API Keys'),
            static::link(OauthClientResource::class, 'OAuth Clients'),
            static::link(ExtensionResource::class, 'Extensions'),
            static::page(Settings::class, 'General Settings'),
            // Updates lives in Utilities, as it does on the reference ("Update WHMCS").
        ]);
    }

    /**
     * Everything the menus above did not claim, so the panel stays complete by construction
     * rather than by remembering to edit this file when an extension is added.
     */
    private static function addons(): ?NavigationGroup
    {
        $panel = Filament::getCurrentOrDefaultPanel();

        if (!$panel) {
            return null;
        }

        $items = [];

        foreach ($panel->getResources() as $resource) {
            if (isset(static::$placed[$resource])) {
                continue;
            }

            $items[] = static::link($resource, static::labelFor($resource));
        }

        foreach ($panel->getPages() as $page) {
            // The dashboard is the brand logo's destination, not a menu entry.
            if (isset(static::$placed[$page]) || is_subclass_of($page, Dashboard::class)) {
                continue;
            }

            $items[] = static::page($page, static::labelFor($page));
        }

        return static::group('Addons', 'ri-puzzle-line', $items);
    }

    // ── plumbing ────────────────────────────────────────────────────────────────

    /**
     * Null when every entry was filtered out — an empty dropdown reads as breakage.
     *
     * @param  array<NavigationItem|null>  $items
     */
    private static function group(string $label, string $icon, array $items): ?NavigationGroup
    {
        $items = array_values(array_filter($items));

        if ($items === []) {
            return null;
        }

        return NavigationGroup::make($label)->icon($icon)->items($items);
    }

    /**
     * Null if the resource is absent or barred. `class_exists` is what decides, not the
     * `use` above — an import loads nothing — so a removed extension drops out of the menu
     * rather than fatalling the panel.
     *
     * @param  array<string, mixed>  $params
     */
    private static function link(
        string $resource,
        string $label,
        string $page = 'index',
        array $params = [],
        ?\Closure $badge = null,
        string|\Closure|null $badgeColor = null,
    ): ?NavigationItem {
        if (!class_exists($resource)) {
            return null;
        }

        if (!static::reachable($resource)) {
            return null;
        }

        $permitted = $page === 'create'
            ? $resource::canCreate()
            : $resource::canViewAny();

        if (!$permitted) {
            return null;
        }

        // Claimed even when this particular entry is a second link to the same resource:
        // "Pending Orders" and "Active Orders" both being ServiceResource is exactly why
        // the Addons catch-all must not add a third, unfiltered copy of it.
        static::$placed[$resource] = true;

        $url = static::resolveUrl(fn (): string => $resource::getUrl($page, $params));

        if ($url === null) {
            return null;
        }

        $item = NavigationItem::make($label)
            ->url($url)
            // Two links to the same resource would otherwise both highlight. Nothing here
            // can tell which filter is applied, so neither claims to be the active one.
            ->isActiveWhen(fn (): bool => false);

        return static::withBadge($item, $badge, $badgeColor);
    }

    /**
     * Whether the *screen* is open, not merely whether the record policy allows it. Core
     * overrides `canAccess()` on three resources for reasons a policy cannot express —
     * `TaxRateResource` closes when `settings.tax_enabled` is off, `ErrorLog` and `HttpLog`
     * need `admin.debug_logs.view` — and Filament enforces it on the request. Checking only
     * the policy put Tax Rates in the Billing menu on an install with tax off, answering 403.
     */
    private static function reachable(string $resource): bool
    {
        return !method_exists($resource, 'canAccess') || $resource::canAccess();
    }

    /** A link to a standalone page, same rules as {@see link()}. */
    private static function page(string $page, string $label): ?NavigationItem
    {
        // `canAccess()` comes from the CanAuthorizeAccess trait, which a page is not
        // obliged to use — a page without it authorises nothing and is simply reachable.
        if (!class_exists($page)) {
            return null;
        }

        if (method_exists($page, 'canAccess') && !$page::canAccess()) {
            return null;
        }

        static::$placed[$page] = true;

        $url = static::resolveUrl(fn (): string => $page::getUrl());

        if ($url === null) {
            return null;
        }

        return NavigationItem::make($label)
            ->url($url)
            ->isActiveWhen(fn (): bool => request()->url() === $url);
    }

    /**
     * Resolve the URL now and drop the entry if it cannot be built. **This guard took the
     * admin down once:** `NavigationItem::url()` accepts a closure, so a bad URL fails while
     * the topbar *renders* — an unhandled 500 on every admin page — not while the menu is
     * assembled. So "the navigation builds" proves nothing; the URLs must resolve too.
     *
     * Caught rather than special-cased, because any route taking a parameter has this problem
     * (the Addons catch-all swept in `ClientSummary`, which needs a customer id).
     */
    private static function resolveUrl(\Closure $url): ?string
    {
        try {
            return $url();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** A number or nothing, never a zero: a column of grey zeroes is noise. */
    private static function withBadge(NavigationItem $item, ?\Closure $badge, string|\Closure|null $color): NavigationItem
    {
        if (!$badge) {
            return $item;
        }

        // Colour is the second argument of badge(), not a setter of its own.
        //
        // Wrapped for the same reason as resolveUrl(): a badge closure is evaluated while
        // the topbar renders, so a counting query that throws — a table an extension has
        // not migrated yet, say — would be a 500 on every admin page rather than a missing
        // number. A menu that cannot count is still a usable menu.
        return $item->badge(
            function () use ($badge): ?string {
                try {
                    return ($count = $badge()) ? (string) $count : null;
                } catch (\Throwable $e) {
                    return null;
                }
            },
            $color,
        );
    }

    /** The resource's or page's own navigation label, so nothing has to be renamed twice. */
    private static function labelFor(string $class): string
    {
        $label = method_exists($class, 'getNavigationLabel') ? $class::getNavigationLabel() : null;

        return filled($label) ? $label : str(class_basename($class))->beforeLast('Resource')->headline()->toString();
    }
}
