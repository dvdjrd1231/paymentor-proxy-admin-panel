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
use Illuminate\Support\Facades\Schema;
use Paymenter\Extensions\Others\ProvisioningOps\Models\ProvisioningOperation;

/**
 * Every figure the admin dashboard shows, in one place.
 *
 * Kept out of the widgets so the two of them cannot drift — "unpaid invoices" has to mean
 * the same thing in the action queue as it does on the invoice list it links to — and so
 * the definitions can be read and corrected without touching any presentation.
 *
 * The status vocabulary is core's own, verified against the resource forms rather than
 * assumed: invoices are paid|pending|cancelled, services active|pending|suspended|cancelled,
 * tickets open|replied|closed.
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
     * Money taken in a period, keyed by currency code.
     *
     * Read from transactions rather than invoices for two reasons: `Invoice::$total` is
     * computed from its items in PHP and cannot be summed in SQL, and an invoice's paid
     * date is not stored — the transaction's is.
     *
     * Credit transactions are excluded: paying an invoice from account credit spends money
     * that was already counted as income when the credit was bought. Counting both would
     * book the same payment twice.
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
     * Value still owed on unpaid invoices, keyed by currency code.
     *
     * Invoice totals live in the items, so this sums those and subtracts what has already
     * been received against each invoice — a part-paid invoice contributes only its
     * remainder, which is the number an operator chasing payment actually wants.
     *
     * The aggregates are deliberately not aliased `total`: `Invoice::total()` is an
     * accessor that recomputes the figure from the invoice's items, and Eloquent's `pluck`
     * runs a column through the model's accessors — so a column called `total` comes back
     * as the accessor's value on an itemless model, i.e. 0, silently discarding the SUM.
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
     * Counts already answered during this request.
     *
     * The queue counts are asked for more than once per page: the sidebar badges render on
     * every admin page, the unpaid badge needs the overdue count as well to pick its colour,
     * and on the dashboard the action queue asks for the same figures again. None of these
     * columns is indexed on `status`, so repeating them is the one place this could get
     * expensive. Per-request only — nothing is cached between requests, so the numbers are
     * never stale.
     *
     * @var array<string, int>
     */
    private static array $counted = [];

    private static function remember(string $key, callable $count): int
    {
        return static::$counted[$key] ??= $count();
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
     * Administrators seen in the last few minutes — WHMCS's "Staff Online" panel.
     *
     * Read from `user_sessions`, which core's session middleware stamps with
     * `last_activity` at most once a minute (UserSession::LAST_ACTIVITY_UPDATE). The window
     * has to be comfortably wider than that stamp interval or a colleague reading a page
     * would blink out of the list between writes.
     *
     * Grouped in PHP rather than SQL because one person signed in from two devices has two
     * sessions and should appear once, at whichever is the more recent.
     *
     * @return \Illuminate\Support\Collection<int, object{name: string, last_activity: Carbon}>
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
