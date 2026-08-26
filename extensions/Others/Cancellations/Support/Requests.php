<?php

namespace Paymenter\Extensions\Others\Cancellations\Support;

use App\Jobs\Server\TerminateJob;
use App\Models\Service;
use App\Models\ServiceCancellation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Acting on a cancellation request.
 *
 * ## The gap this closes
 *
 * `service_cancellations.type` is `immediate` or `end_of_period`, and **nothing in core
 * reads it**. The only place a cancellation is consulted at all is the invoicing branch of
 * `app:cron-job`:
 *
 * ```php
 * if ($service->invoices()->where('status','pending')->exists() || $service->cancellation()->exists()) {
 *     return;   // no renewal invoice
 * }
 * ```
 *
 * So every request, whichever type the customer chose, does exactly one thing: it stops the
 * next invoice. A customer who asks to cancel **immediately** keeps a working proxy until the
 * expiry ladder catches up — two days to suspend, fourteen more to terminate — and on a
 * one-time plan, whose `expires_at` is NULL, for ever.
 *
 * There is also no way for an administrator to accept or refuse one: core's list offers Edit
 * and Delete, and deleting is indistinguishable from refusing.
 */
class Requests
{
    /**
     * Honour an immediate request: terminate now, release the proxies now.
     *
     * The request row is kept, not deleted. It is why the service ended, and a terminated
     * service with no record of who asked is the question support cannot answer later.
     */
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

    /**
     * Refuse the request: the service goes back to renewing as though it had never been made.
     *
     * Deleting the row is what does that — core decides "is this service cancelled" by the
     * row's existence, so removing it is the whole of un-cancelling. The audit trail is
     * `owen-it/laravel-auditing`, which core already applies to this model.
     */
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
}
