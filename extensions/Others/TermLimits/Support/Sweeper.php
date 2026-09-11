<?php

namespace Paymenter\Extensions\Others\TermLimits\Support;

use App\Models\CronStat;
use App\Models\Service;
use Illuminate\Support\Facades\Log;
use Paymenter\Extensions\Others\TermLimits\Models\ServiceTerm;
use Paymenter\Extensions\Others\TermLimits\TermLimits;

/** One pass over the terms that are due. */
class Sweeper
{
    /** The reference's **Fixed Term Terminations** task, and the key it reports under. */
    public const STAT_KEY = 'fixed_term_terminations';

    /**
     * @return array{stopped: int, released: int, failed: int, lines: array<int, string>}
     */
    public static function run(bool $dryRun = false): array
    {
        $due = Terms::due();
        $terminate = static::terminates();

        $stopped = 0;
        $released = 0;
        $failed = 0;
        $lines = [];

        foreach ($due as $term) {
            $service = $term->service;
            $label = 'service #' . $term->service_id
                . ' (' . ($service?->product?->name ?? 'product gone') . ')'
                . ' due ' . $term->ends_at->toDateTimeString();

            // A term whose service already stopped for some other reason — cancelled by the
            // customer, terminated by an admin — is closed without touching the panel. Those
            // proxies are already back; terminating again would release what is not
            // allocated. It still has to be closed, or it stays due for ever and the sweeper
            // reconsiders it every minute for the life of the install.
            if (!Terms::isLive($term)) {
                $lines[] = 'release   ' . $label . ' — service is ' . ($service?->status ?? 'deleted');

                if (!$dryRun) {
                    Terms::release($term);
                }

                $released++;

                continue;
            }

            $lines[] = ($terminate ? 'terminate ' : 'suspend   ') . $label;

            if ($dryRun) {
                $stopped++;

                continue;
            }

            // Counted rather than thrown. One panel that will not answer must not stop the
            // other fifty services being ended, and the reference reports a failure count
            // per task for exactly this reason — a task can half-work, and a page that only
            // showed successes would call that a good day.
            try {
                Terms::close($term, $terminate);
                $stopped++;
            } catch (\Throwable $exception) {
                Log::error('TermLimits: could not close term #' . $term->id, [
                    'exception' => $exception->getMessage(),
                ]);
                $failed++;
            }
        }

        if (!$dryRun) {
            static::record($stopped, $failed);
        }

        return ['stopped' => $stopped, 'released' => $released, 'failed' => $failed, 'lines' => $lines];
    }

    /**
     * Report the pass to `cron_stats`, which is where core's own tasks report and therefore
     * where Automation Status reads.
     */
    private static function record(int $stopped, int $failed): void
    {
        CronStat::create(['key' => static::STAT_KEY, 'value' => $stopped, 'date' => now()->toDateString()]);
        CronStat::create(['key' => static::STAT_KEY . '_failed', 'value' => $failed, 'date' => now()->toDateString()]);
    }

    /** Terms for services that were already live when this was installed. */
    public static function backfill(): int
    {
        $opened = 0;

        // No `whereDoesntHave`: that relation would have to live on core's Service model,
        // and this extension does not edit core. One id list is cheap — there is a row here
        // per fixed-term service, not per service.
        $already = ServiceTerm::pluck('service_id')->all();

        Service::query()
            ->where('status', Service::STATUS_ACTIVE)
            ->whereNotIn('id', $already ?: [0])
            ->with(['plan', 'product'])
            ->chunkById(200, function ($services) use (&$opened): void {
                foreach ($services as $service) {
                    if (Terms::open($service)) {
                        $opened++;
                    }
                }
            });

        return $opened;
    }

    /** Whether expiry terminates or suspends. */
    public static function terminates(): bool
    {
        try {
            return (new TermLimits)->config('terminate_on_expiry') !== 'suspend';
        } catch (\Throwable) {
            // Settings unreadable — during install, say. Terminating is the documented
            // default and the one that does not leave proxies allocated.
            return true;
        }
    }
}
