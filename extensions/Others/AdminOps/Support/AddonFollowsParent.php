<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

use App\Jobs\Server\SuspendJob;
use App\Jobs\Server\TerminateJob;
use App\Jobs\Server\UnsuspendJob;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Paymenter\Extensions\Others\AdminOps\Models\ServiceAddon;

/**
 * An addon stops when the service it is attached to stops.
 *
 * Leandro, 2026-10-02: "um addon esta vinculado a um serviço pai, sempre, ou então ele
 * deixa de ser um addon ... então a finalização dele deve acontecer com o serviço pai, ou
 * então ele continuara gerando cobrança."
 *
 * Billing was already nested — an addon carries no expires_at of its own, because the
 * parent's cycle is what it is charged on. Its lifecycle was not: nothing watched the
 * parent, so a terminated service left its addons running. Service #100 was cancelled on
 * 2026-09-12 and its addons #107 and #108 stayed active for another three and thirteen
 * days, and were only picked up then by core's unpaid-order sweep, which would not have
 * touched a paid one at all.
 *
 * Suspension is mirrored too, both ways. A parent suspended for non-payment whose addon
 * keeps serving is the same hole one level down, and unsuspending the parent has to give
 * the addon back or paying up would leave the client worse off than before.
 *
 * Hung on the model rather than on any one screen: a parent is stopped by TermLimits, by
 * core's cron, by the cancellation flow and by an admin on the service page, and all four
 * end in a status write.
 */
class AddonFollowsParent
{
    /** Guards against a cascade re-entering while it is running. */
    private static bool $running = false;

    /** What the parent moved to, and what that does to the addon. */
    private const FOLLOWS = [
        Service::STATUS_CANCELLED => TerminateJob::class,
        Service::STATUS_SUSPENDED => SuspendJob::class,
        Service::STATUS_ACTIVE => UnsuspendJob::class,
    ];

    public static function register(): void
    {
        Service::updated(function (Service $service): void {
            try {
                self::handle($service);
            } catch (\Throwable $exception) {
                // The parent's own change stands either way. An addon left behind is a
                // provisioning problem, and Others/ProvisioningOps is where those surface.
                Log::error('AdminOps: could not carry service #' . $service->id . ' through to its addons', [
                    'exception' => $exception->getMessage(),
                ]);
            }
        });
    }

    public static function handle(Service $service): void
    {
        if (self::$running || !$service->wasChanged('status')) {
            return;
        }

        $job = self::FOLLOWS[$service->status] ?? null;

        if ($job === null) {
            return;
        }

        // Unsuspending is the one case that must not reach for every addon: only the ones
        // this parent took down with it should come back, not one cancelled on its own.
        $from = $service->status === Service::STATUS_ACTIVE
            ? [Service::STATUS_SUSPENDED]
            : [Service::STATUS_ACTIVE, Service::STATUS_SUSPENDED];

        $addons = Service::query()
            ->whereIn('id', ServiceAddon::where('parent_service_id', $service->id)->pluck('service_id'))
            ->whereIn('status', $from)
            ->get();

        if ($addons->isEmpty()) {
            return;
        }

        self::$running = true;

        try {
            foreach ($addons as $addon) {
                $addon->update(['status' => $service->status]);

                // After commit, so the panel is never called against rows a rollback would
                // take back — the same order TermLimits::close() uses.
                DB::afterCommit(fn () => $job::dispatch($addon));
            }
        } finally {
            self::$running = false;
        }
    }
}
