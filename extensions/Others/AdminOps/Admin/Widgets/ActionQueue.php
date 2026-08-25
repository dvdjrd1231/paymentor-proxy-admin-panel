<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Widgets;

use App\Admin\Resources\InvoiceResource;
use App\Admin\Resources\InvoiceTransactions\InvoiceTransactionResource;
use App\Admin\Resources\ServiceResource;
use App\Admin\Resources\TicketResource;
use Filament\Widgets\Concerns\CanPoll;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Others\AdminOps\Support\Links;
use Paymenter\Extensions\Others\AdminOps\Support\Metrics;

/**
 * The work waiting to be done, most urgent first — every line a link to the exact list
 * that shows it.
 *
 * This is the part of WHMCS's homepage that makes it usable: you do not go looking for
 * pending orders, overdue invoices and unanswered tickets across five screens, they come
 * to you already counted. Rows with nothing in them are omitted rather than shown as zero,
 * so an empty queue reads as "nothing to do" at a glance instead of as four zeroes to
 * check.
 *
 * @link docs/02b-admin-area.md
 */
class ActionQueue extends Widget
{
    use CanPoll;

    protected string $view = 'adminops::widgets.action-queue';

    protected int|string|array $columnSpan = 'full';

    /** Directly under At a glance, above core's trend widgets. */
    protected static ?int $sort = -2;

    /**
     * Overridden rather than set through the trait's `$pollingInterval` property: a class
     * that uses CanPoll directly and also redeclares that property is a fatal composition
     * error in PHP 8.3. (Core's widgets get away with it because they inherit the property
     * from StatsOverviewWidget instead of composing the trait themselves.)
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
        $rows = [];

        // Ordered by how badly each one hurts if it is left alone: a broken provisioning
        // run means a customer has paid and received nothing, an unanswered ticket is a
        // customer waiting, and money owed comes after both.
        $provisioning = Metrics::provisioningFailures();

        if ($provisioning) {
            $rows[] = [
                'count' => $provisioning,
                'label' => 'Provisioning failures',
                'note' => 'Paid for, not delivered — retry from the operations list',
                'tone' => 'danger',
                'url' => Links::provisioning(),
            ];
        }

        if ($awaiting = Metrics::ticketsAwaitingReply()) {
            $rows[] = [
                'count' => $awaiting,
                'label' => 'Tickets awaiting reply',
                'note' => 'The customer wrote last',
                'tone' => 'warning',
                'url' => TicketResource::getUrl('index', ['tab' => 'open']),
            ];
        }

        if ($pending = Metrics::servicesPending()) {
            $rows[] = [
                'count' => $pending,
                'label' => 'Services awaiting provisioning',
                'note' => 'Ordered but not yet active',
                'tone' => 'info',
                'url' => ServiceResource::getUrl('index', [
                    'filters' => ['status' => ['value' => 'pending']],
                ]),
            ];
        }

        if ($suspended = Metrics::servicesSuspended()) {
            $rows[] = [
                'count' => $suspended,
                'label' => 'Suspended services',
                'note' => 'Usually non-payment — unsuspend once settled',
                'tone' => 'warning',
                'url' => ServiceResource::getUrl('index', [
                    'filters' => ['status' => ['value' => 'suspended']],
                ]),
            ];
        }

        if ($failedPayments = Metrics::paymentsFailed()) {
            $rows[] = [
                'count' => $failedPayments,
                'label' => 'Failed payments (7 days)',
                'note' => 'Repeated failures usually mean a gateway is misconfigured',
                'tone' => 'danger',
                // The transaction list has no status filter in core and already sorts
                // newest first, so the recent failures are at the top of an unfiltered list.
                'url' => InvoiceTransactionResource::getUrl('index'),
            ];
        }

        if ($unpaid = Metrics::invoicesUnpaid()) {
            $overdue = Metrics::invoicesOverdue();

            $rows[] = [
                'count' => $unpaid,
                'label' => 'Unpaid invoices',
                // The overdue figure rides along on this row rather than getting one of its
                // own: core can filter invoices by status but not by "pending *and* past
                // due", so a separate row would link to a list that did not match its count.
                'note' => $overdue
                    ? $overdue . ' of them ' . ($overdue === 1 ? 'is' : 'are') . ' past the due date'
                    : 'Issued and not yet settled — none overdue',
                'tone' => $overdue ? 'danger' : 'neutral',
                'url' => InvoiceResource::getUrl('index', [
                    'filters' => ['status' => ['value' => 'pending']],
                    'sort' => 'due_at',
                ]),
            ];
        }

        if ($expiring = Metrics::servicesExpiring()) {
            $rows[] = [
                'count' => $expiring,
                'label' => 'Services due within 7 days',
                'note' => 'Renewal invoices are raised by the cron',
                'tone' => 'neutral',
                'url' => ServiceResource::getUrl('index', [
                    'filters' => ['status' => ['value' => 'active']],
                ]),
            ];
        }

        return ['rows' => $rows];
    }
}
