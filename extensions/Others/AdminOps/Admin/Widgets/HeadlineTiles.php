<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Widgets;

use App\Admin\Resources\ServiceCancellationResource;
use App\Admin\Resources\ServiceResource;
use App\Admin\Resources\TicketResource;
use Filament\Widgets\Concerns\CanPoll;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Others\AdminOps\Support\Links;
use Paymenter\Extensions\Others\AdminOps\Support\Metrics;

/**
 * The four tiles across the top of WHMCS's homepage.
 *
 * The client pointed at that screen and asked for it, and these are the part of it people
 * actually recognise: pending orders, tickets waiting, pending cancellations, pending
 * module actions — each a number big enough to read from across a desk, each a link into
 * the list behind it.
 *
 * They overlap with **Needs attention** below, and that is deliberate rather than an
 * oversight. The tiles are a fixed set of four, always in the same place, showing zeroes as
 * zeroes: you learn where "tickets waiting" lives and read it without looking. The queue is
 * the opposite — variable length, ordered by urgency, zero rows omitted — and it carries
 * six more measures the tiles have no room for (failed payments, unpaid invoices,
 * renewals). One is a gauge, the other is a to-do list.
 *
 * "Pending module actions" is our provisioning failure count. WHMCS means the same thing by
 * it: a server module call that did not complete and is waiting on a human.
 *
 * @link docs/02b-admin-area.md
 */
class HeadlineTiles extends Widget
{
    use CanPoll;

    protected string $view = 'adminops::widgets.headline-tiles';

    protected int|string|array $columnSpan = 'full';

    /**
     * Above everything, shortcuts included: on the reference this row is the first thing
     * under the page title.
     */
    protected static ?int $sort = -5;

    /**
     * Overridden rather than set through the trait's `$pollingInterval` property: a class
     * that uses CanPoll directly and also redeclares that property is a fatal composition
     * error in PHP 8.3.
     */
    public function getPollingInterval(): ?string
    {
        return '120s';
    }

    public static function canView(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.widgets.overview');
    }

    protected function getViewData(): array
    {
        $tiles = [
            [
                'count' => Metrics::servicesPending(),
                'label' => 'Pending Orders',
                'icon' => 'heroicon-o-shopping-cart',
                'tone' => 'success',
                'url' => ServiceResource::canViewAny()
                    ? ServiceResource::getUrl('index', ['filters' => ['status' => ['value' => 'pending']]])
                    : null,
            ],
            [
                'count' => Metrics::ticketsAwaitingReply(),
                'label' => 'Tickets Waiting',
                'icon' => 'heroicon-o-chat-bubble-left-right',
                'tone' => 'brand',
                'url' => TicketResource::canViewAny()
                    ? TicketResource::getUrl('index', ['tab' => 'open'])
                    : null,
            ],
            [
                'count' => Metrics::cancellationsPending(),
                'label' => 'Pending Cancellations',
                'icon' => 'heroicon-o-no-symbol',
                'tone' => 'warning',
                'url' => ServiceCancellationResource::canViewAny()
                    ? ServiceCancellationResource::getUrl('index')
                    : null,
            ],
        ];

        // Only when ProvisioningOps is installed. Without it there is no record of a failed
        // server call to count, and a permanent "0" would claim there is nothing wrong when
        // in fact nothing is being watched.
        $provisioning = Metrics::provisioningFailures();

        if ($provisioning !== null) {
            $tiles[] = [
                'count' => $provisioning,
                'label' => 'Pending Module Actions',
                'icon' => 'heroicon-o-exclamation-triangle',
                'tone' => 'info',
                'url' => Links::provisioning(),
            ];
        }

        return ['tiles' => $tiles];
    }
}
