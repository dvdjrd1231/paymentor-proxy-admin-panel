<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

use App\Admin\Pages\CronStats;
use App\Admin\Pages\Updates;
use App\Admin\Resources\ApiResource;
use App\Admin\Resources\CategoryResource;
use App\Admin\Resources\ConfigOptionResource;
use App\Admin\Resources\CurrencyResource;
use App\Admin\Resources\CustomPropertyResource;
use App\Admin\Resources\ExtensionResource;
use App\Admin\Resources\GatewayResource;
use App\Admin\Resources\InvoiceResource;
use App\Admin\Resources\NotificationTemplateResource;
use App\Admin\Resources\OauthClientResource;
use App\Admin\Resources\OrderResource;
use App\Admin\Resources\ProductResource;
use App\Admin\Resources\RoleResource;
use App\Admin\Resources\ServerResource;
use App\Admin\Resources\ServiceResource;
use App\Admin\Resources\TicketResource;
use App\Admin\Resources\UserResource;
use App\Models\DebugLog;
use App\Models\FailedJob;
use Illuminate\Support\Facades\Schema;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\SystemSettings;
use Paymenter\Extensions\Servers\ProxyPanel\Admin\Pages\PanelLocations;

/**
 * The icon clusters at each end of WHMCS's menu bar: the `+` at the start, and search, cogs,
 * updater, wrench, account and help at the end.
 *
 * Which of those are links and which are menus was read off the reference's own template
 * (`admin/templates/blend/nav.tpl`, `ul.right-nav`) — a screenshot cannot show the difference.
 * The cogs and the updater are plain links; the wrench and help are menus. The account menu is
 * Filament's own, moved into position by the skin rather than rebuilt here, so its sign-out
 * keeps working.
 *
 * Permission checks and URL resolution happen here, not in the view: this renders on every
 * admin page, so an unbuildable link must vanish rather than throw. See
 * {@see WhmcsNavigation::resolveUrl()}.
 *
 * @link docs/02b-admin-area.md
 */
class Toolbar
{
    /** @return array<int, array{label: string, url: string}> */
    public static function quickCreate(): array
    {
        return array_values(array_filter([
            static::create(UserResource::class, 'New Client'),
            static::create(OrderResource::class, 'New Order'),
            static::create(InvoiceResource::class, 'New Invoice'),
            static::create(ServiceResource::class, 'New Service'),
            static::create(TicketResource::class, 'New Ticket'),
            static::create(ProductResource::class, 'New Product'),
        ]));
    }

    /**
     * The end-of-bar icons, in the reference's order. Each entry is a `link` or a `menu`;
     * either is dropped when nothing in it resolved, since an empty dropdown reads as breakage
     * rather than as a role that cannot go there.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function utilities(): array
    {
        return array_values(array_filter([
            static::iconLink('health', 'heroicon-o-cog-6-tooth', 'Automation Status', CronStats::class, static::health()),
            static::iconLink('updates', 'heroicon-o-arrow-down-tray', 'Update Paymenter', Updates::class),
            static::menu('setup', 'heroicon-o-wrench-screwdriver', 'Setup', static::setup(), grid: true),
            static::menu('help', 'heroicon-o-question-mark-circle', 'Help', static::help()),
        ]));
    }

    /**
     * The wrench menu — every setup screen, because the bar has no Setup menu and WHMCS's
     * "Setup index page" has no Paymenter equivalent. Ordered the way a store gets built, not
     * alphabetically.
     *
     * @return array<int, array{label: string, url: string, icon: ?string, target: ?string}>
     */
    private static function setup(): array
    {
        return array_values(array_filter([
            // WHMCS's own Configuration screen is a landing grid of named areas, not one
            // long form — core's Settings page is the latter, so the wrench now leads to
            // System Settings instead, whose first tile is that same page for whoever
            // wants the raw form directly. See {@see SystemSettings}.
            static::page(SystemSettings::class, 'System Settings', 'heroicon-o-adjustments-horizontal'),
            static::index(ProductResource::class, 'Products', 'heroicon-o-cube'),
            static::index(CategoryResource::class, 'Categories', 'heroicon-o-squares-2x2'),
            static::index(ConfigOptionResource::class, 'Configurable Options', 'heroicon-o-adjustments-vertical'),
            static::index(ServerResource::class, 'Servers', 'heroicon-o-server-stack'),
            static::page(PanelLocations::class, 'Panel Locations', 'heroicon-o-globe-alt'),
            static::index(GatewayResource::class, 'Payment Gateways', 'heroicon-o-credit-card'),
            static::index(CurrencyResource::class, 'Currencies', 'heroicon-o-banknotes'),
            static::index(CustomPropertyResource::class, 'Custom Properties', 'heroicon-o-tag'),
            static::index(NotificationTemplateResource::class, 'Email Templates', 'heroicon-o-envelope'),
            static::index(RoleResource::class, 'Administrator Roles', 'heroicon-o-user-group'),
            static::index(ApiResource::class, 'API Keys', 'heroicon-o-key'),
            static::index(OauthClientResource::class, 'OAuth Clients', 'heroicon-o-finger-print'),
            static::index(ExtensionResource::class, 'Extensions', 'heroicon-o-puzzle-piece'),
        ]));
    }

    /**
     * The reference's five help entries in its order, pointed at Paymenter's own resources.
     * Its last is the licence; ours is the version, which answers the same question.
     *
     * @return array<int, array{label: string, url: string, icon: ?string, target: ?string}>
     */
    private static function help(): array
    {
        return array_values(array_filter([
            static::external('Documentation', 'https://paymenter.org/docs'),
            static::external('Technical Support', 'https://github.com/Paymenter/Paymenter/issues'),
            static::external('Community Forums', 'https://discord.gg/paymenter'),
            static::external("What's New", 'https://github.com/Paymenter/Paymenter/releases'),
            static::page(Updates::class, 'Version Information', separated: true),
        ]));
    }

    /**
     * The cogs badge: failed jobs plus exceptions from the last week. Windowed because
     * `debug_logs` is never pruned and a badge that only grows is one nobody reads. Fully
     * guarded — this renders on every admin page, so a throwing count would be a panel-wide
     * 500 rather than a missing number.
     */
    public static function health(): ?int
    {
        try {
            $count = 0;

            if (Schema::hasTable('failed_jobs')) {
                $count += FailedJob::query()->count();
            }

            if (Schema::hasTable('debug_logs')) {
                $count += DebugLog::query()
                    ->where('type', 'exception')
                    ->where('created_at', '>=', now()->subDays(7))
                    ->count();
            }

            return $count ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ── plumbing ────────────────────────────────────────────────────────────────

    /** @return array<string, mixed>|null */
    private static function iconLink(string $key, string $icon, string $label, string $page, ?int $badge = null): ?array
    {
        $entry = static::page($page, $label);

        if ($entry === null) {
            return null;
        }

        return [
            'type' => 'link',
            'key' => $key,
            'icon' => $icon,
            'label' => $label,
            'badge' => $badge,
            'url' => $entry['url'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>|null
     */
    private static function menu(string $key, string $icon, string $label, array $items, bool $grid = false): ?array
    {
        if ($items === []) {
            return null;
        }

        return [
            'type' => 'menu',
            'key' => $key,
            'icon' => $icon,
            'label' => $label,
            'badge' => null,
            'grid' => $grid,
            'items' => $items,
        ];
    }

    /**
     * `canCreate()` answers the policy, not whether a `create` route exists — a resource may
     * authorise creation and only offer it in a modal — so the URL is resolved here too.
     *
     * @return array{label: string, url: string, icon: ?string, target: ?string, separated: bool}|null
     */
    private static function create(string $resource, string $label): ?array
    {
        if (!class_exists($resource) || !static::reachable($resource) || !$resource::canCreate()) {
            return null;
        }

        return static::entry($label, fn (): string => $resource::getUrl('create'));
    }

    /**
     * @return array{label: string, url: string, icon: ?string, target: ?string, separated: bool}|null
     */
    private static function index(string $resource, string $label, ?string $icon = null): ?array
    {
        if (!class_exists($resource) || !static::reachable($resource) || !$resource::canViewAny()) {
            return null;
        }

        return static::entry($label, fn (): string => $resource::getUrl('index'), $icon);
    }

    /**
     * Core gates some resources in `canAccess()` rather than the policy — skipping it builds
     * entries that answer 403. See {@see WhmcsNavigation::reachable()}.
     */
    private static function reachable(string $resource): bool
    {
        return !method_exists($resource, 'canAccess') || $resource::canAccess();
    }

    /**
     * @return array{label: string, url: string, icon: ?string, target: ?string, separated: bool}|null
     */
    private static function page(string $page, string $label, ?string $icon = null, bool $separated = false): ?array
    {
        // A page need not use CanAuthorizeAccess; without it there is nothing to authorise.
        if (!class_exists($page)) {
            return null;
        }

        if (method_exists($page, 'canAccess') && !$page::canAccess()) {
            return null;
        }

        return static::entry($label, fn (): string => $page::getUrl(), $icon, separated: $separated);
    }

    /**
     * @return array{label: string, url: string, icon: ?string, target: ?string, separated: bool}
     */
    private static function external(string $label, string $url): array
    {
        return [
            'label' => $label,
            'url' => $url,
            'icon' => null,
            'target' => '_blank',
            'separated' => false,
        ];
    }

    /**
     * @return array{label: string, url: string, icon: ?string, target: ?string, separated: bool}|null
     */
    private static function entry(string $label, \Closure $url, ?string $icon = null, bool $separated = false): ?array
    {
        try {
            return [
                'label' => $label,
                'url' => $url(),
                'icon' => $icon,
                'target' => null,
                'separated' => $separated,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }
}
