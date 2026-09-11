<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Widgets;

use Filament\Widgets\Concerns\CanPoll;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Others\AdminOps\Support\Metrics;

/**
 * WHMCS's "Staff Online" and "Client Activity" panels, merged into one.
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
