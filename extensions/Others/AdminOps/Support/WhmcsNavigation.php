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
use Paymenter\Extensions\Others\Affiliates\Admin\Resources\AffiliateResource;
use Paymenter\Extensions\Others\Announcements\Admin\Resources\AnnouncementResource;
use Paymenter\Extensions\Others\GatewayRules\Admin\Resources\GatewayRuleResource;
use Paymenter\Extensions\Others\Knowledgebase\Admin\Resources\KbArticleResource;
use Paymenter\Extensions\Others\Knowledgebase\Admin\Resources\KbCategoryResource;
use Paymenter\Extensions\Others\PaymentFees\Admin\Resources\PaymentFeeRuleResource;
use Paymenter\Extensions\Others\ProvisioningOps\Admin\Resources\ProvisioningOperationResource;
use Paymenter\Extensions\Others\TicketTools\Admin\Resources\CannedResponseResource;
use Paymenter\Extensions\Others\TicketTools\Admin\Resources\TicketNoteResource;
use Paymenter\Extensions\Servers\ProxyPanel\Admin\Pages\PanelLocations;

/**
 * The WHMCS admin menu bar, rebuilt over Paymenter's resources.
 *
 * WHMCS groups the admin by **what you are doing** — Clients, Orders, Billing, Support,
 * Reports, Setup — where Paymenter groups it by *what kind of record it is* (Administration,
 * Configuration, Extensions, System). The client asked for the former, so this maps one onto
 * the other.
 *
 * ## Why a NavigationBuilder and not per-resource groups
 *
 * A resource's group is a static property on the resource class, so regrouping the panel the
 * obvious way would mean editing two dozen core files — exactly what this repo does not do.
 * `Panel::navigation()` takes the whole navigation over in one call, from outside, and is
 * the only way to do this without touching core.
 *
 * ## The catch-all matters
 *
 * Taking navigation over means anything not named here becomes **unreachable** — no menu
 * entry, no sidebar, nothing. Hand-listing alone would therefore quietly hide every resource
 * a future extension registers. So {@see addons()} runs last, diffs what the panel actually
 * has against what was placed, and puts the remainder under **Addons**. Adding an extension
 * still Just Works; it lands in Addons until somebody files it somewhere better.
 *
 * Every entry is permission-checked. A support-only role sees a shorter menu rather than
 * links that greet them with a 403.
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
     * The WHMCS menus, built once per request.
     *
     * Public and memoised because the left rail needs them too: on the reference, the rail
     * lists the section you are *in* — Support pages show the Support links, Reports pages
     * show the reports — so it is the same structure viewed a second way. Building it once
     * and sharing it means the menu and the rail cannot disagree about what exists or about
     * who may see it, and the permission checks and URL resolution behind it run once rather
     * than twice per page.
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

        return static::$groups = array_values(array_filter([
            static::clients(),
            static::orders(),
            static::billing(),
            static::support(),
            static::reports(),
            static::utilities(),
            static::setup(),
            // Last, and only last: it can only work out the remainder once the rest have
            // claimed what they wanted.
            static::addons(),
        ]));
    }

    /**
     * The group the current page belongs to, or null on the dashboard.
     *
     * Matched on path rather than on Filament's own "active" flag: menu entries deliberately
     * report themselves inactive ({@see link()}) because several of them point at the same
     * resource under different filters, so there is nothing to ask. The longest matching
     * path wins, so `/admin/services/cancellations` picks the cancellations entry rather
     * than the `/admin/services` one it happens to start with.
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
            static::link(UserResource::class, 'View/Search Clients'),
            static::link(UserResource::class, 'Add New Client', page: 'create'),
            static::link(ServiceResource::class, 'Products/Services'),
            static::link(
                ServiceCancellationResource::class,
                'Cancellation Requests',
                badge: fn () => Metrics::cancellationsPending(),
                badgeColor: 'danger',
            ),
            static::link(AffiliateResource::class, 'Manage Affiliates'),
        ]);
    }

    /**
     * Orders by status.
     *
     * The status filters point at **services**, not orders: a Paymenter order carries no
     * status of its own — it is a container row holding the services bought together — so
     * "Pending Orders" can only mean a service that is pending. Same reasoning as
     * {@see Metrics::newServices()}.
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
            static::link(InvoiceResource::class, 'All Invoices'),
            static::link(
                InvoiceResource::class,
                'Unpaid Invoices',
                params: ['filters' => ['status' => ['value' => 'pending']], 'sort' => 'due_at'],
                badge: fn () => Metrics::invoicesUnpaid(),
                badgeColor: fn () => Metrics::invoicesOverdue() ? 'danger' : 'gray',
            ),
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
     * WHMCS's Reports menu.
     *
     * Paymenter has no business-reporting module, so there are no report *pages* to list.
     * What it has is the data those reports would be built from, and Filament lists take
     * filters in the URL — so each entry here is the list a given report would have been a
     * view of, pre-filtered. "Income" is the transactions that succeeded; "New Customers"
     * is the customer list newest-first.
     *
     * The alternative was leaving Reports out, which is what the merged "Reports & Logs"
     * menu did. The reference has both menus and staff look for them by name, so an honest
     * set of filtered lists beats a missing menu.
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

    /**
     * WHMCS's Utilities menu — the operational record of what the system did.
     *
     * This is what the merged menu actually contained: logs, the job queue, the cron, the
     * updater. On the reference these live under Utilities, not Reports, and that is where
     * staff go looking for them.
     */
    private static function utilities(): ?NavigationGroup
    {
        return static::group('Utilities', 'ri-tools-line', [
            static::page(Updates::class, 'Update Paymenter'),
            static::page(CronStats::class, 'Automation Status'),
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
            static::link(ProductResource::class, 'Products'),
            static::link(CategoryResource::class, 'Categories'),
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
     * Everything the menus above did not claim.
     *
     * Without this, taking navigation over would hide any resource or page this file has not
     * heard of — including every one a future extension adds. Anything left lands here, so
     * the panel stays complete by construction rather than by remembering to edit this file.
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
     * A group, or null when every entry in it was filtered out.
     *
     * An empty dropdown is worse than a missing one: it looks like the panel is broken
     * rather than like the role simply cannot reach those screens.
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
     * A link to a resource page, or null if the resource is absent or barred.
     *
     * `class_exists` is what decides, not the `use` above — an import is a compile-time
     * alias and loads nothing — so a disabled or removed extension drops out of the menu
     * instead of fatalling the whole panel.
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
     * Resolve a menu URL now, and drop the entry if it cannot be built.
     *
     * **This is the guard that took the admin down once.** `NavigationItem::url()` accepts a
     * closure, so a URL that cannot be generated does not fail while the menu is being
     * assembled — it fails later, while the topbar is being *rendered*, and a route error
     * there is an unhandled 500 on every admin page. Verifying that the navigation "builds"
     * therefore proves nothing; the URLs have to be resolved too, which is what this does.
     *
     * The concrete case: `ClientSummary` is a per-customer screen at
     * `admin/client-summary/{record}`. It is reached from a row on the customer list, never
     * from a menu, so `getUrl()` with no record throws "Missing required parameter". The
     * Addons catch-all, whose whole job is to sweep up pages nobody filed, swept it in.
     *
     * Catching rather than special-casing that one page: any resource or page whose route
     * takes a parameter has the same problem, including ones that do not exist yet, and a
     * menu entry that cannot be linked is simply not a menu entry.
     */
    private static function resolveUrl(\Closure $url): ?string
    {
        try {
            return $url();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Badges show a number or nothing, never a zero — the same rule the sidebar queues use.
     * A row of grey zeroes down a menu is noise you learn to stop reading.
     */
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
