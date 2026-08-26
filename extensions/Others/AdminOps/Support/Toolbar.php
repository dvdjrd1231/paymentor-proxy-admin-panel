<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

use App\Admin\Pages\CronStats;
use App\Admin\Pages\Settings;
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
use Paymenter\Extensions\Servers\ProxyPanel\Admin\Pages\PanelLocations;

/**
 * The two icon clusters that flank WHMCS's menu bar.
 *
 * The reference bar is not only menus. It opens with a **+** that creates any record from
 * anywhere, and closes with the icons an operator reaches for when the thing they want is not
 * a customer record. {@see WhmcsNavigation} rebuilt the menus; this is the rest of that bar.
 *
 * ## The reference's own order, and which of them are menus
 *
 * Read off `admin/templates/blend/nav.tpl` (`ul.right-nav`) rather than from the screenshots,
 * because the difference between a link and a dropdown is not visible in a screenshot:
 *
 * | icon           | reference behaviour                                                  |
 * |----------------|----------------------------------------------------------------------|
 * | magnifier      | Filament's global search, collapsed to a magnifier by the skin       |
 * | cogs (badged)  | **a link** to Automation Status — not a menu                         |
 * | download arrow | **a link** to the updater, and only shown when one is available      |
 * | wrench         | a menu, laid out as a grid of icon tiles (`ul.drop-icons`)           |
 * | avatar         | the account menu — where **Logout** lives                            |
 * | question mark  | a menu of documentation and support links                            |
 *
 * The account menu is not built here: the panel already renders Filament's own user menu in
 * that slot, wired to Paymenter's logout action, so the skin moves it into place between the
 * wrench and the help icon rather than duplicating it.
 *
 * Everything is permission-checked and URL-resolved **here**, not in the view, for the reason
 * spelled out at {@see WhmcsNavigation::resolveUrl()}: this renders on every admin page, so a
 * link that cannot be built has to disappear rather than throw while the topbar renders.
 *
 * @link docs/02b-admin-area.md
 */
class Toolbar
{
    /**
     * The **+** menu — create any record without first navigating to its list.
     *
     * @return array<int, array{label: string, url: string}>
     */
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
     * The icons at the end of the bar, in the reference's order.
     *
     * Each entry is either `type => 'link'` (icon, tooltip, destination) or `type => 'menu'`
     * (icon, tooltip, items). A menu whose items all resolved to nothing is dropped, on the
     * same reasoning as {@see WhmcsNavigation::group()}: an empty dropdown reads as a broken
     * panel rather than as a role that cannot go there. A link is dropped the same way when
     * its target is unreachable.
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
     * The wrench menu — every setup screen in the panel.
     *
     * Longer than the reference's six tiles, and it has to be: WHMCS's wrench opens onto a
     * *Setup index page* that holds the rest, which Paymenter has no equivalent of, and
     * {@see WhmcsNavigation::groups()} drops the Setup menu from the bar to match the
     * reference. Everything that lived there is therefore reachable only from here, so this
     * carries the full list in the reference's tile layout rather than six of them in the
     * reference's exact wording.
     *
     * Ordered the way an operator builds a store — what you sell, where it runs, how you are
     * paid, then the administrative plumbing — not alphabetically.
     *
     * @return array<int, array{label: string, url: string, icon: ?string, target: ?string}>
     */
    private static function setup(): array
    {
        return array_values(array_filter([
            static::page(Settings::class, 'General Settings', 'heroicon-o-adjustments-horizontal'),
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
     * The question mark — the reference's help menu, pointed at Paymenter's own resources.
     *
     * Same five entries in the same order, because staff read this menu by position: docs,
     * somewhere to report a problem, somewhere to ask, what changed, and then — below a rule,
     * as on the reference — what this installation actually is. WHMCS's last entry is its
     * licence; ours is the version and updater, which is the equivalent question here.
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
     * What the reference's red badge on the cogs actually counts.
     *
     * Two things an operator has to be told about without going to look: a queued job that
     * gave up, and an unhandled exception. Errors are windowed to a week because the table is
     * never pruned — an incident from March is history, not an alert, and a badge that only
     * ever grows is one nobody reads.
     *
     * Both tables are guarded and the whole thing is caught: this number renders on every
     * admin page, so a counting query that throws would be a 500 across the panel rather than
     * a missing badge.
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

    /**
     * A bare icon that goes straight somewhere, as the reference's cogs and updater do.
     *
     * @return array<string, mixed>|null
     */
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
     * A resource's create page, or nothing if the role may not create one.
     *
     * `canCreate()` answers the *policy*, not whether a `create` route was registered — a
     * resource may authorise creation and still only offer it in a modal — so the URL is
     * resolved here and the entry dropped if it cannot be built. Same trap as
     * {@see Rail::shortcut()}.
     *
     * @return array{label: string, url: string, icon: ?string, target: ?string, separated: bool}|null
     */
    private static function create(string $resource, string $label): ?array
    {
        if (!class_exists($resource) || !$resource::canCreate()) {
            return null;
        }

        return static::entry($label, fn (): string => $resource::getUrl('create'));
    }

    /**
     * @return array{label: string, url: string, icon: ?string, target: ?string, separated: bool}|null
     */
    private static function index(string $resource, string $label, ?string $icon = null): ?array
    {
        if (!class_exists($resource) || !$resource::canViewAny()) {
            return null;
        }

        return static::entry($label, fn (): string => $resource::getUrl('index'), $icon);
    }

    /**
     * @return array{label: string, url: string, icon: ?string, target: ?string, separated: bool}|null
     */
    private static function page(string $page, string $label, ?string $icon = null, bool $separated = false): ?array
    {
        // `canAccess()` comes from the CanAuthorizeAccess trait, which a page is not obliged
        // to use — a page without it authorises nothing and is simply reachable.
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
