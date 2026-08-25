<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

use App\Admin\Resources\InvoiceResource;
use App\Admin\Resources\OrderResource;
use App\Admin\Resources\ServiceResource;
use App\Admin\Resources\TicketResource;
use App\Admin\Resources\UserResource;
use Illuminate\Support\Facades\Auth;

/**
 * The data behind WHMCS's left sidebar column — Shortcuts, System Information, Advanced
 * Search, Staff Online.
 *
 * Kept out of the Blade view so the view only decides how it looks, and so the queries can
 * be read and corrected in one place. Same reasoning as {@see Metrics}, which this leans on
 * for anything that is a count.
 *
 * Unlike the dashboard widgets, this renders on **every** admin page, so it is deliberately
 * cheap: three counts (already memoised per request by Metrics) and one session lookup.
 *
 * @link docs/02b-admin-area.md
 */
class Rail
{
    /**
     * The section the current page is in — the reference's contextual rail.
     *
     * On WHMCS the left column is not fixed furniture: open a Support page and it lists the
     * Support screens, open Reports and it lists the reports. It is the menu you are already
     * inside, kept in reach so you can move around a section without going back up to the
     * menu bar.
     *
     * Reuses the menu groups rather than repeating them, so a screen can never appear in one
     * and not the other, and so the permission checks behind them are applied once.
     *
     * @return array{label: string, items: array<int, array{label: string, url: string, badge: ?string}>}|null
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

        return ['label' => $group->getLabel(), 'items' => $items];
    }

    /**
     * @return array<int, array{label: string, url: string}>
     */
    public static function shortcuts(): array
    {
        return array_values(array_filter([
            static::shortcut(UserResource::class, 'Add New Client'),
            static::shortcut(OrderResource::class, 'Add New Order'),
            static::shortcut(InvoiceResource::class, 'Create Invoice'),
            static::shortcut(TicketResource::class, 'Open New Ticket'),
            static::shortcut(ServiceResource::class, 'Add New Service'),
        ]));
    }

    /**
     * One shortcut, or nothing if the role may not create that record — or if the resource
     * has no create page to link to.
     *
     * `canCreate()` answers whether the *policy* allows it, not whether a `create` route
     * exists; a resource can authorise creation and still only offer it through a modal.
     * Asking for a route that was never registered throws, and this rail renders on **every**
     * admin page, so that would be a 500 across the whole panel rather than one absent link.
     * The same oversight in the menus is what took the admin down once — see
     * {@see WhmcsNavigation::resolveUrl()}.
     *
     * @return array{label: string, url: string}|null
     */
    private static function shortcut(string $resource, string $label): ?array
    {
        if (!class_exists($resource) || !$resource::canCreate()) {
            return null;
        }

        try {
            return ['label' => $label, 'url' => $resource::getUrl('create')];
        } catch (\Throwable $e) {
            return null;
        }
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
     * The four filtered lists WHMCS puts under Advanced Search, each with its count.
     *
     * Rows with nothing in them are kept rather than dropped — unlike the dashboard's work
     * queue. This is a search box, not a to-do list: "0 pending" is a useful answer to
     * "is there anything pending", and a control that appears and disappears between page
     * loads is worse than one that reads zero.
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
