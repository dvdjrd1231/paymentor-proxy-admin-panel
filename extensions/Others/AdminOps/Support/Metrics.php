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

    /**
     * Lower bound for an "all time" period.
     *
     * Every measure below is a `whereBetween`, so the all-time column is one more call with
     * a start date old enough to predate any row, rather than a parallel set of unbounded
     * queries that could drift from the dated ones. The Unix epoch is safely inside MySQL's
     * `DATETIME` range and comfortably before Paymenter existed.
     */
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

    /**
     * Services ordered in a period — WHMCS's "New Orders" row.
     *
     * Counted from services rather than from `orders`, because a Paymenter order carries no
     * status and no total: it is a container row holding the services that were bought
     * together. The service is the thing that gets provisioned, billed and renewed, so it
     * is also the thing worth counting.
     */
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

    /**
     * Memoised for the request, then cached for half a minute across requests.
     *
     * The per-request memo was not enough: one admin page is not one request. Filament
     * renders each widget and each Livewire component separately, so a dashboard visit
     * re-ran every badge count ten times over. These are sidebar hints and menu badges —
     * thirty seconds of staleness is invisible, and the queries they replace are not.
     *
     * Cache failures fall through to the query rather than to an empty page: a panel that
     * loads slowly is a nuisance, one that shows a wrong zero is a lie.
     */
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

    /**
     * Unresolved provisioning failures, or null when ProvisioningOps is not installed.
     *
     * Both guards are needed: the class is absent when the extension has been removed, and
     * the table is absent when it is present but has never been enabled (its migration runs
     * from the `installed()` hook).
     */
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

    /**
     * Cancellation requests still waiting on someone — WHMCS's "Pending Cancellations".
     *
     * A `service_cancellations` row carries no status of its own; it is a request, and it
     * stops being outstanding when the service it names actually reaches `cancelled`. An
     * end-of-period request therefore stays in this count for the rest of the term, which
     * is right: it is work that has not happened yet.
     */
    public static function cancellationsPending(): int
    {
        return static::remember(__FUNCTION__, fn () => ServiceCancellation::query()
            ->whereHas('service', fn ($query) => $query->where('status', '!=', Service::STATUS_CANCELLED))
            ->count());
    }

    /**
     * Customers, i.e. everyone without a staff role.
     *
     * Staff accounts live in the same table, so counting `users` outright would report
     * every administrator as a new customer.
     */
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

    /**
     * Customers with at least one running service — WHMCS's "Active Clients".
     *
     * Not "customers who have ever bought": someone whose only service was cancelled two
     * years ago is a past customer, and counting them makes the figure grow forever and
     * mean nothing.
     */
    public static function customersActive(): int
    {
        return static::remember(__FUNCTION__, fn () => static::customers()
            ->whereHas('services', fn ($query) => $query->where('status', Service::STATUS_ACTIVE))
            ->count());
    }
}
