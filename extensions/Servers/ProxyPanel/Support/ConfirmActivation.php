<?php

namespace Paymenter\Extensions\Servers\ProxyPanel\Support;

use App\Helpers\ExtensionHelper;
use App\Models\Service;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Ask the panel whether it has finished deploying, rather than waiting to be told.
 *
 * Provisioning is asynchronous: `/newIpv6` returns "accepted", and the service is meant to
 * go Active when the panel calls our callback. On 2026-09-12 service #109 was accepted
 * (remote 1186) and no callback ever arrived — the panel's operators have not wired the
 * URL up yet — so it sat Pending with the proxies already delivered (Leandro, 2026-09-13:
 * "Deployment of the new service was completed … Paymenter does not change the service
 * status to active").
 *
 * The callback still works and is still preferred; this is the belt to its braces. Nothing
 * here decides anything on its own: it calls the module's own syncStatus, which activates
 * only when the panel itself reports the service deployed with endpoints.
 */
class ConfirmActivation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * When to look again, in seconds from provisioning. The panel usually answers within a
     * minute or two; the long tail covers a region that is slow to allocate tunnels.
     */
    public const SCHEDULE = [30, 60, 120, 300, 600, 900, 1800];

    public $tries = 1;

    public function __construct(public Service $service, public int $attempt = 0) {}

    public function handle(): void
    {
        $service = $this->service->fresh();

        // Gone, already live, or cancelled while we waited: nothing left to confirm.
        if (!$service || $service->status !== Service::STATUS_PENDING) {
            return;
        }

        try {
            ExtensionHelper::call($service->product->server, 'syncStatus', [$service]);
        } catch (\Throwable $e) {
            // A panel that is down or slow is not a reason to stop asking.
            Log::warning('[ProxyPanel] Activation check failed', [
                'service' => $service->id,
                'attempt' => $this->attempt,
                'error' => $e->getMessage(),
            ]);
        }

        if ($service->fresh()?->status === Service::STATUS_PENDING) {
            static::schedule($service, $this->attempt + 1);
        }
    }

    /** Queue the next look, if there is one left. */
    public static function schedule(Service $service, int $attempt = 0): void
    {
        if (!isset(self::SCHEDULE[$attempt])) {
            Log::warning('[ProxyPanel] Service still unconfirmed after every check', [
                'service' => $service->id,
                'checks' => count(self::SCHEDULE),
            ]);

            return;
        }

        // The first entry is measured from provisioning, the rest from the check before,
        // so the gaps widen rather than the whole series firing at once.
        $delay = $attempt === 0
            ? self::SCHEDULE[0]
            : self::SCHEDULE[$attempt] - self::SCHEDULE[$attempt - 1];

        static::dispatch($service, $attempt)->delay(now()->addSeconds($delay));
    }
}
