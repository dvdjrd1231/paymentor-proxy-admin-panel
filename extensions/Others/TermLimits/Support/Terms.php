<?php

namespace Paymenter\Extensions\Others\TermLimits\Support;

use App\Helpers\NotificationHelper;
use App\Jobs\Server\SuspendJob;
use App\Jobs\Server\TerminateJob;
use App\Models\Plan;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Paymenter\Extensions\Others\TermLimits\Console\EnforceTerms;
use Paymenter\Extensions\Others\TermLimits\Models\ProductTerm;
use Paymenter\Extensions\Others\TermLimits\Models\ServiceTerm;
use Paymenter\Extensions\Others\TermLimits\Models\ServiceTermExtension;

/**
 * Opening, extending and closing the clock on a fixed-term service.
 *
 * Everything that decides how long a service runs is here, so there is one answer to
 * "why did this proxy stop" and one place to change it.
 */
class Terms
{
    /** Hours in each of core's billing units. A month is not one of them — see {@see length()}. */
    private const HOURS = [
        'hour' => 1,
        'day' => 24,
        'week' => 168,
    ];

    /**
     * The contracted length of a service, in hours — or null if it is not fixed-term.
     *
     * Fixed-term means a **one-time** plan with a period: that is exactly what a daily or
     * weekly proxy is, and exactly what a recurring monthly one is not. A `free` plan is
     * left alone deliberately; whatever a free service is for, cutting it off on a clock
     * nobody paid for is a decision for whoever set it up, not a default.
     *
     * Months are excluded on purpose rather than by omission. The brief makes monthly
     * products the renewable kind, and "one month in hours" is not a number — 28 days in
     * February and 31 in March — so a monthly term would be wrong twice a year.
     */
    public static function length(Service $service): ?int
    {
        // The product's own **Auto Terminate/Fixed Term** wins, exactly as it does on the
        // reference, and it applies whatever the plan is. That is the case derivation cannot
        // reach: "monthly plan, terminates after 3 days" is a free trial, and no billing
        // cycle can express it.
        $override = $service->product_id
            ? ProductTerm::firstWhere('product_id', $service->product_id)?->hours()
            : null;

        if ($override !== null) {
            return $override;
        }

        $plan = $service->plan;

        if (!$plan instanceof Plan || $plan->type !== 'one-time') {
            return null;
        }

        $unit = self::HOURS[$plan->billing_unit] ?? null;
        $period = (int) $plan->billing_period;

        return ($unit && $period > 0) ? $unit * $period : null;
    }

    /**
     * Tell the customer their term has ended - the reference's **Termination Email**.
     *
     * A fixed-term proxy that simply stops working, with nothing said, is a support ticket
     * every time. The reference puts a template picker beside the field for this reason, and
     * this sends the one that product names, falling back to core's `server_terminated`.
     *
     * Never allowed to fail the termination. The service is already stopped and the panel has
     * already released it by the time this runs; an unreachable mail server must not make the
     * sweeper retry a service it has correctly ended.
     */
    public static function notify(Service $service): void
    {
        try {
            $template = $service->product_id
                ? ProductTerm::firstWhere('product_id', $service->product_id)?->termination_email
                : null;

            NotificationHelper::sendNotification(
                $template ?: 'server_terminated',
                ['service' => $service],
                $service->user,
            );
        } catch (\Throwable $exception) {
            Log::warning('TermLimits: could not send the termination email for service #' . $service->id, [
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Start the clock, once, when the service goes live.
     *
     * Keyed on the service, so a second call — a re-provision, a status flapping between
     * pending and active, the sweeper and a webhook arriving together — finds the existing
     * term and leaves it alone. The clock a customer paid for starts once.
     *
     * The start is *now*, not the order date: the brief measures "usage hours equivalent to
     * the contracted period", and an order that waited a day for provisioning has not used
     * any of them.
     */
    public static function open(Service $service): ?ServiceTerm
    {
        $hours = self::length($service);

        if ($hours === null) {
            return null;
        }

        $existing = ServiceTerm::firstWhere('service_id', $service->id);

        if ($existing) {
            return $existing;
        }

        return ServiceTerm::create([
            'service_id' => $service->id,
            'hours' => $hours,
            'started_at' => now(),
            'ends_at' => now()->addHours($hours),
        ]);
    }

    /**
     * Grant extra time, with a reason, on the record.
     *
     * From `ends_at`, not from now: an outage that cost a customer six hours costs them six
     * hours wherever in the term it happened, and extending from now would quietly shorten
     * or lengthen the term depending on when the ticket was answered.
     *
     * A term that has already run out can still be extended — that is the common case, since
     * the customer usually notices when the proxy stops. Reviving the service itself is a
     * separate, deliberate act, because unsuspending a proxy the panel has already released
     * is not something to do as a side effect.
     */
    public static function extend(ServiceTerm $term, int $hours, string $reason, ?User $admin = null): ServiceTermExtension
    {
        return DB::transaction(function () use ($term, $hours, $reason, $admin): ServiceTermExtension {
            $extension = $term->extensions()->create([
                'admin_id' => $admin?->id,
                'hours' => $hours,
                'reason' => $reason,
            ]);

            $term->ends_at = $term->ends_at->copy()->addHours($hours);

            // An extension granted after the sweeper closed the term reopens the clock;
            // otherwise the new time would sit on a term nothing looks at any more.
            if (!$term->isOpen() && $term->ends_at->isFuture()) {
                $term->ended_at = null;
                $term->outcome = null;
            }

            $term->save();

            return $extension;
        });
    }

    /**
     * The service's time is up: stop it on the panel and close the term.
     *
     * Closing the term and dispatching the job happen together, and the term is closed
     * *first*. If the panel call fails the job's own retry handles it; if the order were the
     * other way round a failure between them would leave a term the sweeper picks up again
     * on its next pass, terminating the same service every minute.
     *
     * Suspend or terminate is a setting, and both are defensible: terminating releases the
     * proxies back to the panel immediately, which is the point of a fixed term, while
     * suspending keeps the service recoverable if a customer comes back with a good reason
     * an hour later.
     */
    public static function close(ServiceTerm $term, bool $terminate = true): void
    {
        $service = $term->service;

        if (!$service) {
            // The service is gone; the row is a leftover. Close it so it stops being swept.
            $term->update(['ended_at' => now(), 'outcome' => ServiceTerm::OUTCOME_RELEASED]);

            return;
        }

        DB::transaction(function () use ($term, $service, $terminate): void {
            $term->update([
                'ended_at' => now(),
                'outcome' => $terminate ? ServiceTerm::OUTCOME_TERMINATED : ServiceTerm::OUTCOME_SUSPENDED,
            ]);

            $service->update([
                'status' => $terminate ? Service::STATUS_CANCELLED : Service::STATUS_SUSPENDED,
            ]);

            // Not inside the transaction: the panel call must not run before the rows it
            // depends on are committed, or a rollback leaves a live service marked cancelled.
            DB::afterCommit(function () use ($service, $terminate): void {
                try {
                    $terminate
                        ? TerminateJob::dispatch($service)
                        : SuspendJob::dispatch($service);

                    static::notify($service);
                } catch (\Throwable $exception) {
                    // The term is closed either way. A panel that cannot be reached is a
                    // provisioning failure, which Others/ProvisioningOps already surfaces.
                    Log::error('TermLimits: could not stop service #' . $service->id, [
                        'exception' => $exception->getMessage(),
                    ]);
                }
            });
        });
    }

    /**
     * Every open term whose time has run out — including the ones whose service has already
     * stopped for some other reason.
     *
     * Those are not filtered out here on purpose. A term on a service that was cancelled by
     * hand has nothing left to stop, but it does need closing, or it is overdue and open
     * forever and the sweeper reconsiders it every minute for the rest of the install's
     * life. {@see EnforceTerms} sorts the
     * two apart.
     *
     * @return Collection<int, ServiceTerm>
     */
    public static function due()
    {
        return ServiceTerm::query()
            ->whereNull('ended_at')
            ->where('ends_at', '<=', now())
            ->with('service')
            ->get();
    }

    /** Whether this term still has a live service behind it. */
    public static function isLive(ServiceTerm $term): bool
    {
        return $term->service?->status === Service::STATUS_ACTIVE;
    }

    /** Close a term that has nothing left to stop, without calling the panel. */
    public static function release(ServiceTerm $term): void
    {
        $term->update(['ended_at' => now(), 'outcome' => ServiceTerm::OUTCOME_RELEASED]);
    }
}
