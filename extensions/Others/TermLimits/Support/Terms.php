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

/** Opening, extending and closing the clock on a fixed-term service. */
class Terms
{
    /** Hours in each of core's billing units. A month is not one of them — see {@see length()}. */
    private const HOURS = [
        'hour' => 1,
        'day' => 24,
        'week' => 168,
    ];

    /**
     * The grace added to a term derived from the plan, in minutes.
     *
     * Leandro, 2026-10-02: a daily service ends "após 24:30 (vinte quatro horas e trinta
     * minutos) após estar ativo", a weekly one after 168:30. The contracted length is a
     * round 24 or 168 hours; this is the half-hour on top, so a customer whose service went
     * live at 09:00 is not cut off at 08:59:58 the next morning by clock drift.
     *
     * It is not applied to a product's own Auto Terminate override: an admin who types
     * "3 days" means three days, not three days and half an hour.
     */
    private const GRACE_MINUTES = 30;

    /** The contracted length of a service, in hours — or null if it is not fixed-term. */
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

    /** Tell the customer their term has ended - the reference's **Termination Email**. */
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

    /** Start the clock, once, when the service goes live. */
    public static function open(Service $service): ?ServiceTerm
    {
        $hours = self::length($service);

        if ($hours === null) {
            return null;
        }

        // An *open* term, not merely any term. A closed one must not stand in for a live
        // clock: the sweeper only ever looks at terms with no ended_at, so a service that
        // went active again after its term was closed would keep running with nothing left
        // to stop it.
        //
        // That is not hypothetical — it is how services #109 and #113 came to be active
        // with no clock. Their provisioning failed, so they were not active when their time
        // ran out; the sweeper released the term rather than terminating a service that was
        // not running; the retry later brought them up, and this guard handed back the
        // closed row. They have been unmetered ever since.
        //
        // Re-activation therefore starts a fresh full term, which is what the backfill does
        // for an already-running service and for the same reason: the customer did not
        // consume the time they were not given.
        $existing = ServiceTerm::query()
            ->where('service_id', $service->id)
            ->whereNull('ended_at')
            ->first();

        if ($existing) {
            return $existing;
        }

        $startedAt = now();

        return ServiceTerm::create([
            'service_id' => $service->id,
            'hours' => $hours,
            'started_at' => $startedAt,
            // hours is what was sold; ends_at is when it actually stops, grace included.
            'ends_at' => $startedAt->copy()->addHours($hours)->addMinutes(self::graceMinutes($service)),
        ]);
    }

    /** The grace this service's term gets: the half-hour, unless its length was set by hand. */
    public static function graceMinutes(Service $service): int
    {
        $override = $service->product_id
            ? ProductTerm::firstWhere('product_id', $service->product_id)?->hours()
            : null;

        return $override === null ? self::GRACE_MINUTES : 0;
    }

    /** Grant extra time, with a reason, on the record. */
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

    /** The service's time is up: stop it on the panel and close the term. */
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
