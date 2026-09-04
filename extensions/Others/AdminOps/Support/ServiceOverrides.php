<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

use App\Jobs\Server\TerminateJob;
use App\Jobs\Server\UnsuspendJob;
use App\Models\Service;
use Illuminate\Support\Facades\Log;

/**
 * The enforcement half of the Client Profile's Termination Date and Override
 * Auto-Suspend fields (user request, 2026-09-04). Both are service properties; a
 * property nothing reads is a setting that lies, so this sweep is what reads them:
 *
 * - `termination_date` — the reference's Termination Date: the day the service ends,
 *   renewals or not. Once the date has passed, terminate exactly the way the overdue
 *   ladder does (TerminateJob + status), and clear the property so it acts once.
 * - `no_suspend_until` — the reference's Override Auto-Suspend ("do not suspend
 *   until…"): core's cron suspends unconditionally, and nothing in an extension can
 *   argue with it before the fact — so this undoes it right after: a service the
 *   ladder suspended while its override is still ahead is unsuspended again. Expired
 *   overrides are cleared so the next overdue pass acts normally.
 *
 * Hourly, same cadence and guard pattern as the Cancellations sweeper — and like it,
 * failures are logged per-service rather than thrown, so one unreachable panel does not
 * stop the rest of the queue.
 */
class ServiceOverrides
{
    /** @return array{terminated: int, unsuspended: int} */
    public static function sweep(): array
    {
        $terminated = 0;
        $unsuspended = 0;

        // ── Termination Date ────────────────────────────────────────────────────────
        $due = Service::query()
            ->whereIn('status', ['active', 'suspended'])
            ->whereHas('properties', fn ($query) => $query
                ->where('key', 'termination_date')
                ->where('value', '<=', now()->toDateString()))
            ->with('properties')
            ->get();

        foreach ($due as $service) {
            try {
                TerminateJob::dispatch($service);
                $service->update(['status' => 'cancelled']);
                // Acted on — cleared, so a later status correction does not re-fire it.
                $service->properties()->where('key', 'termination_date')->delete();
                $terminated++;
            } catch (\Throwable $exception) {
                Log::error('ServiceOverrides: could not terminate service #' . $service->id, [
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        // ── Override Auto-Suspend ───────────────────────────────────────────────────
        $held = Service::query()
            ->where('status', 'suspended')
            ->whereHas('properties', fn ($query) => $query
                ->where('key', 'no_suspend_until')
                ->where('value', '>', now()->toDateString()))
            ->get();

        foreach ($held as $service) {
            try {
                UnsuspendJob::dispatch($service);
                $service->update(['status' => 'active']);
                $unsuspended++;
            } catch (\Throwable $exception) {
                Log::error('ServiceOverrides: could not unsuspend service #' . $service->id, [
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        // Spent overrides go quietly; keeping them would hold "do not suspend" rows
        // whose date already passed, which reads as protection that is not there.
        // Properties are polymorphic (HasProperties => morphMany(Property, 'model')).
        \App\Models\Property::query()
            ->where('model_type', (new Service)->getMorphClass())
            ->where('key', 'no_suspend_until')
            ->where('value', '<=', now()->toDateString())
            ->delete();

        return ['terminated' => $terminated, 'unsuspended' => $unsuspended];
    }
}
