<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

use App\Enums\InvoiceTransactionStatus;
use App\Models\Invoice;
use App\Models\InvoiceTransaction;
use App\Models\Service;
use App\Models\ServiceCancellation;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Paymenter\Extensions\Others\ProvisioningOps\Models\ProvisioningOperation;

/**
 * Every figure the dashboard shows, in one place, so the tiles, the rail and the menu
 * badges cannot disagree. Each is memoised per request — several widgets ask for the same
 * number — and each returns null rather than throwing, because a dashboard that cannot
 * count is still a dashboard.
 */
class Metrics
{
    /**
     * A ticket whose last message came from the customer is left `open`; core's
     * TicketMessageCreatedListener flips it to `replied` as soon as staff answer. So
     * `open` is exactly WHMCS's "awaiting reply", not merely "not closed".
     */
    public const TICKET_AWAITING_REPLY = 'open';

    /** Lower bound for an "all time" period. */
    public static function beginningOfTime(): Carbon
    {
        return Carbon::createFromTimestampUTC(0);
    }

    /**
     * Money taken in a period, keyed by currency code. Summed per currency rather than
     * converted: there is no stored rate at transaction time, so adding them would invent one.
     *
     * @return array<string, float>
     */
    public static function income(Carbon $from, Carbon $to): array
    {
        return InvoiceTransaction::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_transactions.invoice_id')
            ->where('invoice_transactions.status', InvoiceTransactionStatus::Succeeded)
            ->where('invoice_transactions.is_credit_transaction', false)
            ->whereBetween('invoice_transactions.created_at', [$from, $to])
            ->groupBy('invoices.currency_code')
            ->selectRaw('invoices.currency_code as code, SUM(invoice_transactions.amount) as amount_sum')
            ->pluck('amount_sum', 'code')
            ->map(fn ($total) => (float) $total)
            ->all();
    }

    /**
     * Still owed on unpaid invoices, keyed by currency. Computed from line items rather than a
     * stored total, which core does not keep.
     *
     * @return array<string, float>
     */
    public static function outstanding(): array
    {
        $billed = Invoice::query()
            ->join('invoice_items', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('invoices.status', Invoice::STATUS_PENDING)
            ->groupBy('invoices.currency_code')
            ->selectRaw('invoices.currency_code as code, SUM(invoice_items.price * invoice_items.quantity) as amount_sum')
            ->pluck('amount_sum', 'code');

        $received = Invoice::query()
            ->join('invoice_transactions', 'invoice_transactions.invoice_id', '=', 'invoices.id')
            ->where('invoices.status', Invoice::STATUS_PENDING)
            ->where('invoice_transactions.status', InvoiceTransactionStatus::Succeeded)
            ->groupBy('invoices.currency_code')
            ->selectRaw('invoices.currency_code as code, SUM(invoice_transactions.amount) as amount_sum')
            ->pluck('amount_sum', 'code');

        $outstanding = [];

        foreach ($billed as $code => $total) {
            $outstanding[$code] = (float) $total - (float) ($received[$code] ?? 0);
        }

        return $outstanding;
    }

    public static function newCustomers(Carbon $from, Carbon $to): int
    {
        return static::customers()->whereBetween('created_at', [$from, $to])->count();
    }

    /** Services ordered in a period — WHMCS's "New Orders" row. */
    public static function newServices(Carbon $from, Carbon $to): int
    {
        return Service::query()->whereBetween('created_at', [$from, $to])->count();
    }

    public static function ticketsOpened(Carbon $from, Carbon $to): int
    {
        return Ticket::query()->whereBetween('created_at', [$from, $to])->count();
    }

    /**
     * Per-request memo. Several widgets and the rail ask for the same counts, and each is a
     * query; without this the dashboard runs them repeatedly on one page load.
     *
     * @var array<string, int>
     */
    private static array $counted = [];

    /** Memoised for the request, then cached for half a minute across requests. */
    private static function remember(string $key, callable $count): int
    {
        return static::$counted[$key] ??= (function () use ($key, $count): int {
            try {
                return (int) \Illuminate\Support\Facades\Cache::remember('adminops.metric.' . $key, 30, $count);
            } catch (\Throwable $e) {
                return (int) $count();
            }
        })();
    }

    /** Services waiting to be provisioned. */
    public static function servicesPending(): int
    {
        return static::remember(__FUNCTION__, fn () => Service::query()->where('status', Service::STATUS_PENDING)->count());
    }

    public static function servicesSuspended(): int
    {
        return static::remember(__FUNCTION__, fn () => Service::query()->where('status', Service::STATUS_SUSPENDED)->count());
    }

    public static function servicesActive(): int
    {
        return static::remember(__FUNCTION__, fn () => Service::query()->where('status', Service::STATUS_ACTIVE)->count());
    }

    /**
     * Active services whose next due date has passed but which are still running —
     * the renewals the billing cron has not managed to collect on.
     */
    public static function servicesExpiring(int $withinDays = 7): int
    {
        return Service::query()
            ->where('status', Service::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays($withinDays)])
            ->count();
    }

    public static function invoicesUnpaid(): int
    {
        return static::remember(__FUNCTION__, fn () => Invoice::query()->where('status', Invoice::STATUS_PENDING)->count());
    }

    /** Unpaid and past its due date — WHMCS's "Overdue Invoices". */
    public static function invoicesOverdue(): int
    {
        return static::remember(__FUNCTION__, fn () => Invoice::query()
            ->where('status', Invoice::STATUS_PENDING)
            ->whereDate('due_at', '<', now())
            ->count());
    }

    public static function ticketsAwaitingReply(): int
    {
        return static::remember(__FUNCTION__, fn () => Ticket::query()->where('status', static::TICKET_AWAITING_REPLY)->count());
    }

    /** Payment attempts the gateway refused recently — usually a gateway misconfiguration. */
    public static function paymentsFailed(int $withinDays = 7): int
    {
        return InvoiceTransaction::query()
            ->where('status', InvoiceTransactionStatus::Failed)
            ->where('created_at', '>=', now()->subDays($withinDays))
            ->count();
    }

    /** Unresolved provisioning failures, or null when ProvisioningOps is not installed. */
    public static function provisioningFailures(): ?int
    {
        $model = ProvisioningOperation::class;

        if (!class_exists($model) || !Schema::hasTable('provisioning_operations')) {
            return null;
        }

        return $model::query()
            ->where('status', $model::STATUS_FAILED)
            ->whereNull('resolved_at')
            ->count();
    }

    /** Cancellation requests still waiting on someone — WHMCS's "Pending Cancellations". */
    public static function cancellationsPending(): int
    {
        return static::remember(__FUNCTION__, fn () => ServiceCancellation::query()
            ->whereHas('service', fn ($query) => $query->where('status', '!=', Service::STATUS_CANCELLED))
            ->count());
    }

    /** Customers, i.e. everyone without a staff role. */
    public static function customers()
    {
        return User::query()->whereNull('role_id');
    }

    /** Staff, i.e. everyone with a role. The inverse of {@see customers()}. */
    public static function staff()
    {
        return User::query()->whereNotNull('role_id');
    }

    /**
     * Administrators seen in the last few minutes — WHMCS's "Staff Online". Read from
     * `user_sessions.last_activity`, which the session middleware touches at most once a
     * minute, so "now" is necessarily approximate.
     *
     * @return Collection<int, object{name: string, last_activity: Carbon}>
     */
    public static function staffOnline(int $withinMinutes = 15)
    {
        return static::staff()
            ->whereHas('sessions', fn ($query) => $query->where('last_activity', '>=', now()->subMinutes($withinMinutes)))
            ->with(['sessions' => fn ($query) => $query->orderByDesc('last_activity')->limit(1)])
            ->get()
            ->map(fn (User $user) => (object) [
                'name' => $user->name,
                'last_activity' => $user->sessions->first()?->last_activity,
            ])
            ->sortByDesc('last_activity')
            ->values();
    }

    /** Customers seen in the last hour — WHMCS's "Users Online". */
    public static function customersOnline(int $withinMinutes = 60): int
    {
        return static::customers()
            ->whereHas('sessions', fn ($query) => $query->where('last_activity', '>=', now()->subMinutes($withinMinutes)))
            ->count();
    }

    /** Customers with at least one running service — WHMCS's "Active Clients". */
    public static function customersActive(): int
    {
        return static::remember(__FUNCTION__, fn () => static::customers()
            ->whereHas('services', fn ($query) => $query->where('status', Service::STATUS_ACTIVE))
            ->count());
    }
}
