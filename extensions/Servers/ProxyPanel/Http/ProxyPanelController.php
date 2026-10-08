<?php

namespace Paymenter\Extensions\Servers\ProxyPanel\Http;

use App\Helpers\ExtensionHelper;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/** Customer-facing actions for a provisioned proxy service. */
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
        // Back to the page the customer was on, not to the service's front page. core binds
        // its current view to ?tab=, so naming it here keeps the form, its result and the
        // rail's highlight together — without it, saving a password dropped them on
        // Information with the form gone, which reads as nothing having happened (Leandro,
        // 2026-10-08: "nao esta funcionando").
        $params = [$this->serviceId];

        if ($this->tab !== null) {
            $params['tab'] = $this->tab;
        }

        return redirect()
            ->route('services.show', $params)
            ->with($key, $message ?? '');
    }

    /** The management page a request came from, so the redirect can return to it. */
    private ?string $tab = null;

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
        $this->tab = 'authips';

        // The form is one field with an address per line (Leandro, 2026-10-07). Split here,
        // before validation, so "max:3" and the per-address ip rule still do the work — the
        // field shape changed, the rules did not.
        if ($request->has('ips_text')) {
            $request->merge([
                'ips' => collect(preg_split('/\R/', (string) $request->input('ips_text')))
                    ->map(fn ($line) => trim($line))
                    ->filter()
                    ->values()
                    ->all(),
            ]);
        }

        $validated = $request->validate([
            'ips' => ['array', 'max:3'],
            'ips.*' => ['nullable', 'string', 'ip'],
        ]);

        return $this->run($service, 'clientUpdateAuthIps', [$validated['ips'] ?? []], 'proxypanel.auth_ips_updated');
    }

    public function updatePassword(Request $request, Service $service)
    {
        $service = $this->resolve($service);
        $this->tab = 'password';

        // The panel takes exactly 8 alphanumeric characters and refuses everything else, so
        // catch it here with a readable message rather than letting the panel answer.
        $validated = $request->validate([
            'password' => ['required', 'string', 'size:8', 'alpha_num'],
        ], ['password.size' => __('proxypanel.password_rules'), 'password.alpha_num' => __('proxypanel.password_rules')]);

        return $this->run($service, 'clientUpdatePassword', [$validated['password']], 'proxypanel.password_updated');
    }

    public function updateRotation(Request $request, Service $service)
    {
        $service = $this->resolve($service);
        $this->tab = 'rotation';

        $validated = $request->validate([
            'minutes' => ['required', 'integer', 'min:0', 'max:10080'],
        ]);

        return $this->run($service, 'clientUpdateRotation', [(int) $validated['minutes']], 'proxypanel.rotation_updated');
    }

    /**
     * Reboot, once the customer has confirmed it on its own page.
     *
     * The raw reboot() is called rather than clientReboot(): run() already turns a refusal
     * into a flash, and the wrapper would add a second one.
     */
    public function reboot(Request $request, Service $service)
    {
        $service = $this->resolve($service);
        $this->tab = 'reboot';

        return $this->run($service, 'reboot', [], 'proxypanel.reboot_done');
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
