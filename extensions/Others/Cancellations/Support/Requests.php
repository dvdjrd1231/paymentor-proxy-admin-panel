<?php

namespace Paymenter\Extensions\Others\Cancellations\Support;

use App\Jobs\Server\TerminateJob;
use App\Models\Service;
use App\Models\ServiceCancellation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Acting on a cancellation request. */
class Requests
{
    /** Honour an immediate request: terminate now, release the proxies now. */
    public static function accept(ServiceCancellation $request): void
    {
        $service = $request->service;

        if (!$service || $service->status === Service::STATUS_CANCELLED) {
            return;
        }

        DB::transaction(function () use ($service): void {
            $service->update(['status' => Service::STATUS_CANCELLED]);

            // Outstanding invoices for a service nobody will receive are not debts; core
            // does the same when its own terminate branch fires.
            $service->invoices()->where('status', 'pending')->update(['status' => 'cancelled']);

            if ($service->product?->stock !== null) {
                $service->product->increment('stock', $service->quantity);
            }

            // After commit: the panel call must not run before the rows it depends on are
            // committed, or a rollback leaves a live service marked cancelled.
            DB::afterCommit(function () use ($service): void {
                try {
                    TerminateJob::dispatch($service);
                } catch (\Throwable $exception) {
                    Log::error('Cancellations: could not terminate service #' . $service->id, [
                        'exception' => $exception->getMessage(),
                    ]);
                }
            });
        });
    }

    /** Refuse the request: the service goes back to renewing as though it had never been made. */
    public static function deny(ServiceCancellation $request): void
    {
        $request->delete();
    }

    /**
     * Requests that asked for immediate cancellation and are still waiting on one.
     *
     * @return Collection<int, ServiceCancellation>
     */
    public static function pendingImmediate()
    {
        return ServiceCancellation::query()
            ->where('type', 'immediate')
            ->with('service')
            ->get()
            ->filter(fn (ServiceCancellation $request): bool => $request->service
                && $request->service->status !== Service::STATUS_CANCELLED);
    }

    /**
     * End-of-period requests whose period has now ended.
     *
     * @return Collection<int, ServiceCancellation>
     */
    public static function dueEndOfPeriod()
    {
        return ServiceCancellation::query()
            ->where('type', 'end_of_period')
            ->with('service')
            ->get()
            ->filter(function (ServiceCancellation $request): bool {
                $service = $request->service;

                return $service
                    && $service->status === Service::STATUS_ACTIVE
                    && $service->expires_at !== null
                    && $service->expires_at->endOfDay()->isPast();
            });
    }
}
