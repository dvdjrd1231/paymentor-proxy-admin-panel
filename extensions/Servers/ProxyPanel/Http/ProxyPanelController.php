<?php

namespace Paymenter\Extensions\Servers\ProxyPanel\Http;

use App\Helpers\ExtensionHelper;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * Customer-facing actions for a provisioned proxy service.
 *
 * Core's service page can only call zero-argument extension functions, so the forms in
 * `resources/views/manage.blade.php` post here instead. Every action:
 *
 *   - runs behind `auth` + CSRF (the routes are in the `web` group),
 *   - authorizes with the Service policy, so one customer can never touch another's
 *     service — the service id in the URL is never trusted on its own,
 *   - delegates to the extension, which re-checks the product's permission flags
 *     server-side, and
 *   - reports failures back to the customer rather than throwing a 500.
 */
class ProxyPanelController
{
    /** Authorize and confirm this really is a ProxyPanel service. */
    private function resolve(Service $service): Service
    {
        Gate::authorize('view', $service);

        if (optional(optional($service->product)->server)->extension !== 'ProxyPanel') {
            abort(404);
        }

        return $service;
    }

    private function back(string $key, ?string $message = null)
    {
        return redirect()
            ->route('services.show', $this->serviceId)
            ->with($key, $message ?? '');
    }

    private int|string|null $serviceId = null;

    /**
     * Run an extension call and translate the outcome into a flash message.
     */
    private function run(Service $service, string $function, array $args, string $successKey)
    {
        $this->serviceId = $service->id;

        try {
            ExtensionHelper::callService($service, $function, $args);

            return $this->back('success', __($successKey));
        } catch (\Throwable $e) {
            Log::channel('stack')->warning('[ProxyPanel] client action failed', [
                'service' => $service->id,
                'function' => $function,
                'error' => $e->getMessage(),
            ]);

            return $this->back('error', $e->getMessage());
        }
    }

    public function updateAuthIps(Request $request, Service $service)
    {
        $service = $this->resolve($service);

        $validated = $request->validate([
            'ips' => ['array', 'max:3'],
            'ips.*' => ['nullable', 'string', 'ip'],
        ]);

        return $this->run($service, 'clientUpdateAuthIps', [$validated['ips'] ?? []], 'proxypanel.auth_ips_updated');
    }

    public function updatePassword(Request $request, Service $service)
    {
        $service = $this->resolve($service);

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:64'],
        ]);

        return $this->run($service, 'clientUpdatePassword', [$validated['password']], 'proxypanel.password_updated');
    }

    public function updateRotation(Request $request, Service $service)
    {
        $service = $this->resolve($service);

        $validated = $request->validate([
            'minutes' => ['required', 'integer', 'min:0', 'max:10080'],
        ]);

        return $this->run($service, 'clientUpdateRotation', [(int) $validated['minutes']], 'proxypanel.rotation_updated');
    }

    /** Download the proxy list as a plain-text file. */
    public function export(Service $service)
    {
        $service = $this->resolve($service);

        try {
            $body = ExtensionHelper::callService($service, 'clientExport');
        } catch (\Throwable $e) {
            $this->serviceId = $service->id;

            return $this->back('error', $e->getMessage());
        }

        return response((string) $body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="proxies-' . $service->id . '.txt"',
        ]);
    }
}
