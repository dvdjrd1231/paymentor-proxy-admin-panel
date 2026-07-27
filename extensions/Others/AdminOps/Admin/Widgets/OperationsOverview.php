<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Widgets;

use App\Models\Invoice;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Operational-metrics widget for the admin dashboard (spec item 2), focused on the
 * proxy business: services state, support load, billing and growth at a glance.
 *
 * Modeled on core's StatsOverviewWidget usage (app/Admin/Widgets) so the Filament API
 * matches. Appears on the dashboard once extension widgets are discovered — see
 * docs/CORE-TOUCHPOINTS.md #3 (one line), or place it on a custom admin page.
 */
class OperationsOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '120s';

    protected function getStats(): array
    {
        $activeServices = Service::where('status', 'active')->count();
        $suspended = Service::where('status', 'suspended')->count();
        $openTickets = Ticket::where('status', '!=', 'closed')->count();
        $unpaidInvoices = Invoice::where('status', 'pending')->count();
        $paid30 = Invoice::where('status', 'paid')->where('updated_at', '>=', now()->subDays(30))->count();
        $newCustomers30 = User::where('created_at', '>=', now()->subDays(30))->count();

        return [
            Stat::make('Active services', $activeServices)
                ->description('Currently provisioned')
                ->color('success'),
            Stat::make('Suspended', $suspended)
                ->description('Need attention')
                ->color($suspended > 0 ? 'warning' : 'gray'),
            Stat::make('Open tickets', $openTickets)
                ->description('Awaiting response')
                ->color($openTickets > 0 ? 'warning' : 'gray'),
            Stat::make('Unpaid invoices', $unpaidInvoices)
                ->description('Pending payment')
                ->color($unpaidInvoices > 0 ? 'danger' : 'gray'),
            Stat::make('Paid invoices (30d)', $paid30)
                ->description('Last 30 days')
                ->color('success'),
            Stat::make('New customers (30d)', $newCustomers30)
                ->description('Last 30 days'),
        ];
    }
}
