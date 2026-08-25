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
     * @return array<int, array{label: string, url: string}>
     */
    public static function shortcuts(): array
    {
        $shortcuts = [];

        if (UserResource::canCreate()) {
            $shortcuts[] = ['label' => 'Add New Client', 'url' => UserResource::getUrl('create')];
        }

        if (OrderResource::canCreate()) {
            $shortcuts[] = ['label' => 'Add New Order', 'url' => OrderResource::getUrl('create')];
        }

        if (InvoiceResource::canCreate()) {
            $shortcuts[] = ['label' => 'Create Invoice', 'url' => InvoiceResource::getUrl('create')];
        }

        if (TicketResource::canCreate()) {
            $shortcuts[] = ['label' => 'Open New Ticket', 'url' => TicketResource::getUrl('create')];
        }

        if (ServiceResource::canCreate()) {
            $shortcuts[] = ['label' => 'Add New Service', 'url' => ServiceResource::getUrl('create')];
        }

        return $shortcuts;
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
