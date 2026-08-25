<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

use App\Admin\Pages\CronStats;
use App\Admin\Pages\Settings;
use App\Admin\Pages\Updates;
use App\Admin\Resources\ApiResource;
use App\Admin\Resources\Audits\AuditResource;
use App\Admin\Resources\CurrencyResource;
use App\Admin\Resources\EmailLogResource;
use App\Admin\Resources\ErrorLogResource;
use App\Admin\Resources\ExtensionResource;
use App\Admin\Resources\FailedJobResource;
use App\Admin\Resources\GatewayResource;
use App\Admin\Resources\HttpLogResource;
use App\Admin\Resources\InvoiceResource;
use App\Admin\Resources\NotificationTemplateResource;
use App\Admin\Resources\OrderResource;
use App\Admin\Resources\ProductResource;
use App\Admin\Resources\RoleResource;
use App\Admin\Resources\ServiceResource;
use App\Admin\Resources\TicketResource;
use App\Admin\Resources\UserResource;
use App\Models\DebugLog;
use App\Models\FailedJob;
use Illuminate\Support\Facades\Schema;

/**
 * The two icon clusters that flank WHMCS's menu bar.
 *
 * The reference bar is not only menus. It opens with a **+** that creates any record from
 * anywhere, and closes with five utility icons — search, system health, updates, setup,
 * help — which is where an operator goes when the thing they want is not a customer record.
 * {@see WhmcsNavigation} rebuilt the menus; this is the rest of that bar.
 *
 * Search is not listed here: the panel already has Filament's global search in that slot,
 * and the skin collapses it to the reference's magnifier until it is focused. Re-implementing
 * it would mean giving up the resource-aware results core already provides.
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
     * The utility icons at the end of the bar, in the reference's order.
     *
     * Each is a dropdown rather than a single link, because the reference's icons stand for
     * *areas* — its gear opens system health, its wrench opens the whole Setup menu — and one
     * icon that jumps straight to one page would strand the rest of the area behind a menu
     * the operator has to go and find.
     *
     * A cluster entry with no reachable items is dropped, on the same reasoning as
     * {@see WhmcsNavigation::group()}: an empty dropdown reads as a broken panel rather than
     * as a role that cannot go there.
     *
     * @return array<int, array{key: string, icon: string, label: string, badge: ?int, items: array<int, array{label: string, url: string, target: ?string}>}>
     */
    public static function utilities(): array
    {
        return array_values(array_filter([
            static::cluster('health', 'heroicon-o-cog-6-tooth', 'System health', static::health(), [
                static::index(ErrorLogResource::class, 'Error Log'),
                static::index(FailedJobResource::class, 'Failed Jobs'),
                static::index(HttpLogResource::class, 'HTTP Log'),
                static::index(EmailLogResource::class, 'Email Log'),
                static::index(AuditResource::class, 'Audit Log'),
                static::page(CronStats::class, 'Cron Status'),
            ]),
            static::cluster('updates', 'heroicon-o-arrow-down-tray', 'Updates', null, [
                static::page(Updates::class, 'Check for Updates'),
                static::index(ExtensionResource::class, 'Extensions'),
            ]),
            static::cluster('setup', 'heroicon-o-wrench-screwdriver', 'Setup', null, [
                static::page(Settings::class, 'General Settings'),
                static::index(ProductResource::class, 'Products'),
                static::index(GatewayResource::class, 'Payment Gateways'),
                static::index(CurrencyResource::class, 'Currencies'),
                static::index(NotificationTemplateResource::class, 'Email Templates'),
                static::index(RoleResource::class, 'Administrator Roles'),
                static::index(ApiResource::class, 'API Keys'),
            ]),
            static::cluster('help', 'heroicon-o-question-mark-circle', 'Help', null, [
                ['label' => 'Paymenter Documentation', 'url' => 'https://paymenter.org/docs', 'target' => '_blank'],
                ['label' => 'Community Support', 'url' => 'https://discord.gg/paymenter', 'target' => '_blank'],
            ]),
        ]));
    }

    /**
     * What the reference's red badge on the gear actually counts.
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
     * @param  array<int, array{label: string, url: string, target: ?string}|null>  $items
     * @return array{key: string, icon: string, label: string, badge: ?int, items: array<int, array{label: string, url: string, target: ?string}>}|null
     */
    private static function cluster(string $key, string $icon, string $label, ?int $badge, array $items): ?array
    {
        $items = array_values(array_filter($items));

        if ($items === []) {
            return null;
        }

        return compact('key', 'icon', 'label', 'badge', 'items');
    }

    /**
     * A resource's create page, or nothing if the role may not create one.
     *
     * `canCreate()` answers the *policy*, not whether a `create` route was registered — a
     * resource may authorise creation and still only offer it in a modal — so the URL is
     * resolved here and the entry dropped if it cannot be built. Same trap as
     * {@see Rail::shortcut()}.
     *
     * @return array{label: string, url: string, target: ?string}|null
     */
    private static function create(string $resource, string $label): ?array
    {
        if (!class_exists($resource) || !$resource::canCreate()) {
            return null;
        }

        return static::entry($label, fn (): string => $resource::getUrl('create'));
    }

    /**
     * @return array{label: string, url: string, target: ?string}|null
     */
    private static function index(string $resource, string $label): ?array
    {
        if (!class_exists($resource) || !$resource::canViewAny()) {
            return null;
        }

        return static::entry($label, fn (): string => $resource::getUrl('index'));
    }

    /**
     * @return array{label: string, url: string, target: ?string}|null
     */
    private static function page(string $page, string $label): ?array
    {
        // `canAccess()` comes from the CanAuthorizeAccess trait, which a page is not obliged
        // to use — a page without it authorises nothing and is simply reachable.
        if (!class_exists($page)) {
            return null;
        }

        if (method_exists($page, 'canAccess') && !$page::canAccess()) {
            return null;
        }

        return static::entry($label, fn (): string => $page::getUrl());
    }

    /**
     * @return array{label: string, url: string, target: ?string}|null
     */
    private static function entry(string $label, \Closure $url): ?array
    {
        try {
            return ['label' => $label, 'url' => $url(), 'target' => null];
        } catch (\Throwable $e) {
            return null;
        }
    }
}
