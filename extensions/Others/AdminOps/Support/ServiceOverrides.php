<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

use App\Jobs\Server\TerminateJob;
use App\Jobs\Server\UnsuspendJob;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * The enforcement half of the Client Profile's Termination Date and Override
 * Auto-Suspend fields (user request, 2026-09-04). Both are service properties; a
 * property nothing reads is a setting that lies, so this sweep is what reads them:
 */
class ServiceOverrides
{
    /** @return array{terminated: int, unsuspended: int, exempt: int} */
    public static function sweep(): array
    {
        $terminated = 0;
        $unsuspended = 0;

        // Client groups: Exempt from Suspend & Terminate. Members are lifted back out of
        // suspension on the same pass that handles per-service overrides, so one
        // mechanism does this job rather than two (Leandro, 2026-09-07).
        $exempt = static::liftExemptGroups();

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

        return [
            'terminated' => $terminated,
            'unsuspended' => $unsuspended,
            'exempt' => $exempt,
        ];
    }

    /**
     * Un-suspend every service belonging to a client whose group is exempt from suspend
     * and terminate.
     */
    private static function liftExemptGroups(): int
    {
        if (!Schema::hasTable('ext_client_groups')) {
            return 0;
        }

        $groups = DB::table('ext_client_groups')->where('suspend_exempt', true)->pluck('id');

        if ($groups->isEmpty()) {
            return 0;
        }

        $userIds = DB::table('properties')
            ->where('model_type', (new \App\Models\User)->getMorphClass())
            ->where('key', 'client_group_id')
            ->whereIn('value', $groups->map(fn ($id) => (string) $id))
            ->pluck('model_id');

        if ($userIds->isEmpty()) {
            return 0;
        }

        $lifted = 0;

        foreach (Service::whereIn('user_id', $userIds)->where('status', 'suspended')->get() as $service) {
            try {
                UnsuspendJob::dispatch($service);
                $service->update(['status' => 'active']);
                $lifted++;
            } catch (\Throwable $exception) {
                Log::error('ServiceOverrides: could not lift exempt service #' . $service->id, [
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return $lifted;
    }
}
