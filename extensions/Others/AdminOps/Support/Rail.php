<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

use App\Admin\Resources\InvoiceResource;
use App\Admin\Resources\OrderResource;
use App\Admin\Resources\ServiceResource;
use App\Admin\Resources\TicketResource;
use App\Admin\Resources\UserResource;
use Illuminate\Support\Facades\Auth;

/**
 * The data behind WHMCS's left rail: Shortcuts, System Information, Advanced Search and
 * Staff Online. Structure only — `rail.blade.php` renders it.
 *
 * Everything is permission-checked and URL-resolved here, for the reason at
 * {@see WhmcsNavigation::resolveUrl()}: this renders on every admin page, so a link that
 * cannot be built must vanish rather than throw.
 *
 * @link docs/02b-admin-area.md
 */
class Rail
{
    /**
     * The section the current page is in — the reference's rail lists the menu you are inside,
     * so it is {@see WhmcsNavigation}'s own groups viewed a second way. Sharing them means the
     * rail and the bar cannot disagree about what exists or who may see it.
     *
     * @return array{label: string, icon: string|\BackedEnum|null, items: array<int, array{label: string, url: string, badge: ?string}>}|null
     */
    public static function section(): ?array
    {
        $group = WhmcsNavigation::activeGroup();

        if (!$group) {
            return null;
        }

        $items = [];

        foreach ($group->getItems() as $item) {
            $items[] = [
                'label' => $item->getLabel(),
                'url' => (string) $item->getUrl(),
                'badge' => $item->getBadge(),
            ];
        }

        return [
            'label' => $group->getLabel(),
            // String or BackedEnum, whichever the group was given — the icon component
            // resolves both, so neither is normalised away here.
            'icon' => $group->getIcon(),
            'items' => $items,
        ];
    }

    /**
     * Each with its own small icon, as every WHMCS shortcut has — the icon column is half of
     * what makes that list scannable.
     *
     * @return array<int, array{label: string, url: string, icon: string}>
     */
    public static function shortcuts(): array
    {
        return array_values(array_filter([
            static::shortcut(UserResource::class, 'Add New Client', 'ri-user-add-line'),
            static::shortcut(OrderResource::class, 'Add New Order', 'ri-shopping-cart-2-line'),
            static::shortcut('Paymenter\\Extensions\\Others\\Quotes\\Admin\\Resources\\QuoteResource', 'Create New Quote', 'ri-draft-line'),
            static::shortcut(InvoiceResource::class, 'Create Invoice', 'ri-bill-line'),
            static::shortcut(TicketResource::class, 'Open New Ticket', 'ri-mail-add-line'),
            static::shortcut(ServiceResource::class, 'Add New Service', 'ri-add-box-line'),
        ]));
    }

    /**
     * One shortcut, or nothing if the role may not create that record. `canCreate()` answers
     * the policy, not whether a `create` route exists — a resource may authorise creation and
     * only offer it in a modal — so the URL is resolved here too.
     *
     * @return array{label: string, url: string, icon: string}|null
     */
    private static function shortcut(string $resource, string $label, string $icon): ?array
    {
        if (!class_exists($resource) || !static::reachable($resource) || !$resource::canCreate()) {
            return null;
        }

        try {
            return ['label' => $label, 'url' => $resource::getUrl('create'), 'icon' => $icon];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Core gates some resources in `canAccess()` rather than the policy. Nothing the rail links
     * to does today; guarded so the next one that does cannot reintroduce the 403 the menu had.
     * See {@see WhmcsNavigation::reachable()}.
     */
    private static function reachable(string $resource): bool
    {
        return !method_exists($resource, 'canAccess') || $resource::canAccess();
    }

    /**
     * WHMCS's System Information panel.
     *
     * Its version, licence and expiry lines have no equivalent here and inventing them would
     * be worse than leaving them out, so this carries what is actually true of this
     * installation and useful to know before changing anything.
     *
     * @return array<string, string>
     */
    public static function systemInformation(): array
    {
        return array_filter([
            'Store' => config('app.name'),
            'Paymenter' => config('app.version') ?: null,
            'Laravel' => app()->version(),
            'PHP' => PHP_VERSION,
            'Environment' => ucfirst(app()->environment()),
        ]);
    }

    /**
     * The filtered lists WHMCS puts under Advanced Search, each with its count. Counts come
     * from {@see Metrics} so the rail and the menu badges cannot disagree.
     *
     * @return array<int, array{label: string, count: int, url: string}>
     */
    public static function searches(): array
    {
        try {
            return static::buildSearches();
        } catch (\Throwable $e) {
            // Same rule as the shortcuts: this rail is on every admin page, so a counting
            // query or a route that fails must cost the panel a box, not a 500.
            return [];
        }
    }

    /**
     * @return array<int, array{label: string, count: int, url: string}>
     */
    private static function buildSearches(): array
    {
        $searches = [];

        if (ServiceResource::canViewAny()) {
            $searches[] = [
                'label' => 'Pending services',
                'count' => Metrics::servicesPending(),
                'url' => ServiceResource::getUrl('index', ['filters' => ['status' => ['value' => 'pending']]]),
            ];
            $searches[] = [
                'label' => 'Suspended services',
                'count' => Metrics::servicesSuspended(),
                'url' => ServiceResource::getUrl('index', ['filters' => ['status' => ['value' => 'suspended']]]),
            ];
        }

        if (InvoiceResource::canViewAny()) {
            $searches[] = [
                'label' => 'Unpaid invoices',
                'count' => Metrics::invoicesUnpaid(),
                'url' => InvoiceResource::getUrl('index', ['filters' => ['status' => ['value' => 'pending']], 'sort' => 'due_at']),
            ];
        }

        if (TicketResource::canViewAny()) {
            $searches[] = [
                'label' => 'Tickets awaiting reply',
                'count' => Metrics::ticketsAwaitingReply(),
                'url' => TicketResource::getUrl('index', ['tab' => 'open']),
            ];
        }

        return $searches;
    }

    /**
     * Colleagues signed in right now, the signed-in administrator first.
     *
     * WHMCS lists you in your own Staff Online panel, and it is the one entry that proves
     * the panel is working rather than merely empty.
     *
     * @return array<int, array{name: string, self: bool, seen: ?string}>
     */
    public static function staffOnline(): array
    {
        $me = Auth::user();
        $rows = [];

        if ($me) {
            $rows[] = ['name' => $me->name, 'self' => true, 'seen' => 'now'];
        }

        foreach (Metrics::staffOnline() as $member) {
            if ($me && $member->name === $me->name) {
                continue;
            }

            $rows[] = [
                'name' => $member->name,
                'self' => false,
                'seen' => $member->last_activity?->diffForHumans(),
            ];
        }

        return $rows;
    }
}
