<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Widgets;

use Filament\Widgets\Concerns\CanPoll;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Others\AdminOps\Support\Metrics;

/**
 * WHMCS's "Staff Online" and "Client Activity" panels, merged into one.
 *
 * Two questions an operator asks without thinking about it: is anyone else on the desk
 * right now, and is anybody using the store. Neither has an answer anywhere in Paymenter's
 * admin today, even though core records everything needed for both — `user_sessions`
 * carries a `last_activity` stamp per signed-in device.
 *
 * Kept as one widget rather than two because each is three lines long, and a dashboard of
 * near-empty panels is the clutter this whole extension exists to avoid.
 *
 * @link docs/02b-admin-area.md
 */
class WhoIsAround extends Widget
{
    use CanPoll;

    protected string $view = 'adminops::widgets.who-is-around';

    protected int|string|array $columnSpan = 'full';

    /** Below the queue: useful context, never the thing you open the panel for. */
    protected static ?int $sort = -1;

    /**
     * Overridden rather than set through the trait's `$pollingInterval` property: a class
     * that uses CanPoll directly and also redeclares that property is a fatal composition
     * error in PHP 8.3.
     *
     * A minute, not the two or five the other widgets use — "who is online" is the one
     * figure here that is worthless if it is stale.
     */
    public function getPollingInterval(): ?string
    {
        return '60s';
    }

    public static function canView(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.widgets.overview');
    }

    protected function getViewData(): array
    {
        return [
            'staff' => Metrics::staffOnline(),
            'activeClients' => Metrics::customersActive(),
            'clientsOnline' => Metrics::customersOnline(),
        ];
    }
}
