<?php

namespace Paymenter\Extensions\Others\Cancellations\Support;

use App\Models\CronStat;
use App\Models\ServiceCancellation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Paymenter\Extensions\Others\Cancellations\Cancellations;

/** The reference's **Cancellation Requests** automation task. */
class Sweeper
{
    /** The key this task records under. Reads as "Cancellations processed" on the status page. */
    public const STAT_KEY = 'cancellations_processed';

    /**
     * @return array{terminated: int, failed: int, lines: array<int, string>}
     */
    public static function run(bool $dryRun = false): array
    {
        $terminated = 0;
        $failed = 0;
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

            if ($dryRun) {
                $terminated++;

                continue;
            }

            // Counted rather than thrown, and for the reference's own reason: it reports a
            // failed count per task because a task can half-work, and one panel that will
            // not answer must not stop the rest of the queue being cleared.
            try {
                Requests::accept($request);
                $terminated++;
            } catch (\Throwable $exception) {
                Log::error('Cancellations: could not act on request #' . $request->id, [
                    'exception' => $exception->getMessage(),
                ]);
                $failed++;
            }
        }

        if (!$dryRun) {
            // Recorded even when zero: a task that only writes a row when it does something
            // is indistinguishable, on the status page, from a task that has stopped running.
            CronStat::create(['key' => static::STAT_KEY, 'value' => $terminated, 'date' => now()->toDateString()]);
            CronStat::create(['key' => static::STAT_KEY . '_failed', 'value' => $failed, 'date' => now()->toDateString()]);
        }

        return ['terminated' => $terminated, 'failed' => $failed, 'lines' => $lines];
    }

    /**
     * Everything waiting to be acted on: end-of-period requests that have come due, plus any
     * immediate request still standing.
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
