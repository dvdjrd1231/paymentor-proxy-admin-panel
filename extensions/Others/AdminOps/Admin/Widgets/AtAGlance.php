<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Widgets;

use Filament\Widgets\Concerns\CanPoll;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Others\AdminOps\Support\Metrics;
use Paymenter\Extensions\Others\AdminOps\Support\Money;

/**
 * WHMCS's Overview panel: the business in four rows and three columns.
 *
 * Paymenter's own dashboard widgets answer "how does this month compare with last month",
 * which is a question you ask occasionally. This answers "how are we doing today", which is
 * the one an operator opens the panel to ask — and it is the panel the client pointed at
 * when they said Paymenter's admin lacks usability.
 *
 * @link docs/02b-admin-area.md
 */
class AtAGlance extends Widget
{
    use CanPoll;

    protected string $view = 'adminops::widgets.at-a-glance';

    protected int|string|array $columnSpan = 'full';

    /**
     * Ahead of core's widgets, which stay below as the longer-range view.
     */
    protected static ?int $sort = -3;

    /**
     * Overridden rather than set through the trait's `$pollingInterval` property: a class
     * that uses CanPoll directly and also redeclares that property is a fatal composition
     * error in PHP 8.3.
     */
    public function getPollingInterval(): ?string
    {
        return '300s';
    }

    public static function canView(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.widgets.overview');
    }

    protected function getViewData(): array
    {
        // All time last, as on the reference's Billing panel. Every measure here is a
        // `whereBetween`, so "all time" is expressed as a lower bound old enough to predate
        // any row rather than as a second code path — one query shape, one set of
        // definitions, nothing that can drift between the columns.
        $periods = [
            'Today' => [now()->startOfDay(), now()],
            'This month' => [now()->startOfMonth(), now()],
            'This year' => [now()->startOfYear(), now()],
            'All time' => [Metrics::beginningOfTime(), now()],
        ];

        $rows = [
            ['label' => 'Income', 'money' => true, 'values' => []],
            ['label' => 'New services', 'money' => false, 'values' => []],
            ['label' => 'New customers', 'money' => false, 'values' => []],
            ['label' => 'Tickets opened', 'money' => false, 'values' => []],
        ];

        foreach ($periods as $name => [$from, $to]) {
            $rows[0]['values'][$name] = Money::formatTotals(Metrics::income($from, $to));
            $rows[1]['values'][$name] = Metrics::newServices($from, $to);
            $rows[2]['values'][$name] = Metrics::newCustomers($from, $to);
            $rows[3]['values'][$name] = Metrics::ticketsOpened($from, $to);
        }

        return [
            'periods' => array_keys($periods),
            'rows' => $rows,
            'outstanding' => Money::formatTotals(Metrics::outstanding()),
            'activeServices' => Metrics::servicesActive(),
        ];
    }
}
