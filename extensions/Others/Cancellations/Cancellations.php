<?php

namespace Paymenter\Extensions\Others\Cancellations;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use App\Models\ServiceCancellation;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Paymenter\Extensions\Others\Cancellations\Support\Requests;
use Paymenter\Extensions\Others\Cancellations\Support\Sweeper;
use Throwable;

/** Cancellation requests that are actually acted on. */
#[ExtensionMeta(
    name: 'Cancellation Requests',
    description: 'Acts on immediate cancellations, and lets an administrator accept or refuse a request.',
    version: '1.0.0',
    author: 'Paymenter Proxy Platform',
)]
class Cancellations extends Extension
{
    public function getConfig($values = [])
    {
        return [
            [
                'name' => 'Notice',
                'type' => 'placeholder',
                'label' => new HtmlString(
                    'Core records a cancellation request but never acts on its <b>type</b>: an "immediate" '
                    . 'request only stops the next invoice, so the service keeps running and the proxies stay '
                    . 'allocated. Requests are reviewed under <b>Clients → Cancellation Requests</b>.'
                ),
            ],
            [
                'name' => 'auto_accept_immediate',
                'label' => 'Immediate requests',
                'type' => 'select',
                'options' => [
                    'auto' => 'Terminate straight away — do what the customer asked',
                    'review' => 'Hold for review — an administrator accepts it',
                ],
                'default' => 'auto',
                'description' => 'End-of-period requests are not affected by this — they always end on their '
                    . 'due date, because that is a date arriving rather than a decision to make. This governs '
                    . 'the automatic termination only, exactly as the reference does under Automation Settings.',
            ],
        ];
    }

    public function boot()
    {
        $this->actOnImmediateRequests();
        $this->sweepWhenDue();
    }

    /** The reference's daily **Cancellation Requests** task. */
    private function sweepWhenDue(): void
    {
        app()->booted(function (): void {
            // Guarded because a throw here reaches no handler: `booted()` runs on every
            // request, so an exception while *registering* background work 500s every page
            // of the site — which is exactly what an `Artisan::starting()` that did not
            // exist did on 2026-08-27. A schedule that fails to register costs a background
            // task; an unhandled boot exception costs the whole business.
            try {
                app(Schedule::class)
                    ->call(fn () => Sweeper::run())
                    ->hourly()
                    ->name('cancellations-sweep')
                    ->withoutOverlapping()
                    ->onOneServer();
            } catch (Throwable $exception) {
                Log::error('Cancellations: could not register its scheduled work', [
                    'exception' => $exception->getMessage(),
                ]);
            }
        });
    }

    /** An immediate request, honoured immediately. */
    private function actOnImmediateRequests(): void
    {
        ServiceCancellation::created(function (ServiceCancellation $request): void {
            if ($request->type !== 'immediate') {
                return;
            }

            if ($this->config('auto_accept_immediate') === 'review') {
                return;
            }

            try {
                Requests::accept($request);
            } catch (Throwable $exception) {
                Log::error('Cancellations: could not act on request #' . $request->id, [
                    'exception' => $exception->getMessage(),
                ]);
            }
        });
    }
}
