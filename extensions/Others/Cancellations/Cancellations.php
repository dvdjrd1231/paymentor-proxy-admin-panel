<?php

namespace Paymenter\Extensions\Others\Cancellations;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use App\Models\ServiceCancellation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Paymenter\Extensions\Others\Cancellations\Support\Requests;
use Throwable;

/**
 * Cancellation requests that are actually acted on.
 *
 * ## The gap this closes
 *
 * `service_cancellations.type` is `immediate` or `end_of_period`, and **nothing in core reads
 * it**. The only place a cancellation is consulted at all is the invoicing branch of
 * `app:cron-job`, which skips a service that has one. So every request, whichever type the
 * customer chose, does exactly one thing: it stops the next invoice.
 *
 * A customer who asks to cancel **immediately** therefore keeps a working proxy until the
 * expiry ladder catches up — two days to suspend, fourteen more to terminate — and on a
 * one-time plan, whose `expires_at` is NULL, for ever. Meanwhile the proxies stay allocated
 * on the panel, so the capacity is gone too.
 *
 * There was also no way to accept or refuse one: core's list offers Edit and Delete, and
 * deleting is indistinguishable from refusing.
 *
 * ## What this does
 *
 * - An **immediate** request terminates the service as soon as it is made, if the setting
 *   below says so — which is what the customer asked for and what returns the capacity.
 * - **End of period** requests are left exactly as they are. Core already handles them
 *   correctly by not invoicing again, and terminating one early would take away time the
 *   customer has paid for.
 * - An administrator gets **Accept now** and **Refuse** on every request.
 *
 * Automatic acceptance is a setting rather than a given, because "immediate" is a customer's
 * word: a store that wants a human to look first can have that, and the badge on the menu
 * counts what is waiting.
 */
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
                'description' => 'End-of-period requests are never affected by this: core already handles them '
                    . 'by not raising another invoice, and ending one early would take away time already paid for.',
            ],
        ];
    }

    public function boot()
    {
        $this->actOnImmediateRequests();
    }

    /**
     * An immediate request, honoured immediately.
     *
     * On the model's `created` event rather than in the daily cron, because "immediate" that
     * waits until midnight is not immediate — and every hour it waits is an hour of proxy
     * capacity the panel cannot re-let.
     *
     * A failure here must never stop the request being recorded: the customer's instruction
     * is the important part, the admin list shows it as still running, and the badge counts
     * it as waiting.
     */
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
