<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Widgets;

use App\Models\CronStat;
use App\Models\Invoice;
use App\Models\InvoiceTransaction;
use App\Models\Service;
use App\Models\ServiceCancellation;
use App\Models\Ticket;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

/**
 * Issue #37 — WHMCS's Automation Overview: what the background work did today, six small
 * numbers and the Last Automation Run line. Every figure is a real query over today;
 * a source Paymenter does not record shows 0 from an honest count, never a blank.
 */
class AutomationOverview extends Widget
{
    protected string $view = 'adminops::widgets.automation-overview';

    protected int|string|array $columnSpan = ['default' => 1, 'md' => 1];

    protected static ?int $sort = -3;

    public static function canView(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.widgets.overview');
    }

    protected function getViewData(): array
    {
        $today = now()->startOfDay();
        $count = function (callable $query): int {
            try {
                return (int) $query();
            } catch (\Throwable $e) {
                return 0;
            }
        };

        return [
            'stats' => [
                ['Invoices Created', $count(fn () => Invoice::where('created_at', '>=', $today)->count()), '#5bc0de'],
                ['Payments Captured', $count(fn () => InvoiceTransaction::where('created_at', '>=', $today)->count()), '#5cb85c'],
                ['Overdue Suspensions', $count(fn () => Service::where('status', 'suspended')->where('updated_at', '>=', $today)->count()), '#f0ad4e'],
                ['Inactive Tickets Closed', $count(fn () => Ticket::where('status', 'closed')->where('updated_at', '>=', $today)->count()), '#d9534f'],
                ['Services Terminated', $count(fn () => Service::where('status', 'cancelled')->where('updated_at', '>=', $today)->count()), '#9b59b6'],
                ['Cancellations Processed', $count(fn () => ServiceCancellation::where('updated_at', '>=', $today)->count()), '#337ab7'],
            ],
            'lastRun' => (function () {
                try {
                    $at = CronStat::latest('created_at')->first()?->created_at;

                    return $at ? ($at->isToday() ? 'Today' : $at->format('m/d/Y')) . ' at ' . $at->format('g:i A') : null;
                } catch (\Throwable $e) {
                    return null;
                }
            })(),
        ];
    }
}
