<?php

namespace Paymenter\Extensions\Others\Cancellations\Support;

use App\Models\CronStat;
use App\Models\ServiceCancellation;
use Illuminate\Support\Collection;
use Paymenter\Extensions\Others\Cancellations\Cancellations;

/**
 * The reference's **Cancellation Requests** automation task.
 *
 * WHMCS runs this as one of its daily cron tasks — *"automatically terminate accounts with
 * cancellation requests when due"* — and reports it on Automation Status with a success and
 * a failure count. This is the same task, and it writes to the same `cron_stats` table core
 * uses, so it appears on our Automation Status page beside core's own without that page
 * needing to know this extension exists.
 *
 * Immediate requests are normally already gone by the time this runs: they are acted on as
 * they are made, because a proxy released six hours sooner is six hours of capacity earned,
 * and the reference's once-a-day pass is a limitation of its scheduler rather than a
 * decision. The sweep still picks up any that were missed — a request made while the panel
 * was unreachable, or made in `review` mode and since approved — so there is one path that
 * always catches up.
 */
class Sweeper
{
    /** The key this task records under. Reads as "Cancellations processed" on the status page. */
    public const STAT_KEY = 'cancellations_processed';

    /**
     * @return array{terminated: int, lines: array<int, string>}
     */
    public static function run(bool $dryRun = false): array
    {
        $terminated = 0;
        $lines = [];

        foreach (static::due() as $request) {
            $service = $request->service;

            $lines[] = sprintf(
                'terminate service #%d (%s) — %s request, due %s',
                $request->service_id,
                $service?->product?->name ?? 'product gone',
                $request->type === 'immediate' ? 'immediate' : 'end of period',
                $service?->expires_at?->toDateString() ?? 'now',
            );

            if (!$dryRun) {
                Requests::accept($request);
            }

            $terminated++;
        }

        if (!$dryRun) {
            // Recorded even when it is zero: a task that only writes a row when it does
            // something is indistinguishable, on the status page, from a task that has
            // stopped running. Zero is the useful answer here.
            CronStat::create([
                'key' => static::STAT_KEY,
                'value' => $terminated,
                'date' => now()->toDateString(),
            ]);
        }

        return ['terminated' => $terminated, 'lines' => $lines];
    }

    /**
     * Everything waiting to be acted on: end-of-period requests that have come due, plus any
     * immediate request still standing.
     *
     * In `review` mode only the end-of-period ones are swept. That mirrors the reference,
     * whose switch governs the automatic termination and not the request itself — an
     * immediate request is then a decision for a human, which is the whole point of turning
     * the switch off, while an end-of-period one is a date arriving and needs no decision.
     *
     * @return Collection<int, ServiceCancellation>
     */
    public static function due()
    {
        $due = Requests::dueEndOfPeriod();

        return static::reviewsImmediate()
            ? $due
            : $due->concat(Requests::pendingImmediate());
    }

    /** Whether immediate requests wait for a human. */
    public static function reviewsImmediate(): bool
    {
        try {
            return (new Cancellations)->config('auto_accept_immediate') === 'review';
        } catch (\Throwable) {
            // Settings unreadable — during install, say. The documented default is automatic.
            return false;
        }
    }
}
