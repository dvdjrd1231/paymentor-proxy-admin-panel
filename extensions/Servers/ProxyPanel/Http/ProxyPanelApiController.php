<?php

namespace Paymenter\Extensions\Servers\ProxyPanel\Http;

use App\Helpers\ExtensionHelper;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * The customer API, at the address the WHMCS module served it from.
 *
 * Leandro, 2026-10-08: "sim, alguns clientes usam essa API" / "tudo igual ao whmcs". So the
 * path, the parameters, the action names, the response shape and the error strings are the
 * module's own — read from its api.php, not guessed — because a customer's script must keep
 * working across the migration unchanged.
 *
 * Authentication is the service's API key, as there: no session, no cookie. The key is
 * compared in constant time, and a wrong key answers exactly as a missing service does, so
 * the endpoint cannot be used to find out which service ids exist.
 */
class ProxyPanelApiController
{
    /** The module's own answer shape: a status, then either a reason or the payload. */
    private function reply(bool $ok, string|array $message): JsonResponse
    {
        $body = ['status' => $ok ? 'ok' : 'error'];

        $body = is_string($message)
            ? $body + ['reason' => $message]
            : array_merge($body, $message);

        return response()->json($body);
    }

    public function handle(Request $request): JsonResponse
    {
        $id = $request->input('id');
        $key = $request->input('key');

        if (blank($id)) {
            return $this->reply(false, 'Service id not found');
        }

        if (blank($key)) {
            return $this->reply(false, 'API Key is mandatory');
        }

        $service = Service::with(['product.server', 'configs'])->find($id);

        // One answer for "no such service" and "wrong key", as the module gave: telling them
        // apart would turn this into a way to enumerate service ids.
        if (!$service || !$this->keyMatches($service, (string) $key)) {
            return $this->reply(false, 'Service not found');
        }

        if (optional(optional($service->product)->server)->extension !== 'ProxyPanel') {
            return $this->reply(false, 'Service not found');
        }

        if (blank($this->property($service, 'proxypanel_service_id'))) {
            return $this->reply(false, 'Not properly configured');
        }

        if ($service->status !== Service::STATUS_ACTIVE) {
            return $this->reply(false, 'This service is not active');
        }

        $action = $request->input('a');

        if (blank($action)) {
            return $this->reply(false, 'Action is mandatory');
        }

        try {
            return $this->dispatch($service, (string) $action, $request);
        } catch (\Throwable $exception) {
            Log::channel('stack')->warning('[ProxyPanel] api call failed', [
                'service' => $service->id,
                'action' => $action,
                'error' => $exception->getMessage(),
            ]);

            return $this->reply(false, $exception->getMessage());
        }
    }

    private function dispatch(Service $service, string $action, Request $request): JsonResponse
    {
        $settings = $this->settings($service);

        switch ($action) {
            case 'version':
                return $this->reply(true, 'ProxyPanel v1.0');

            case 'info':
                return $this->reply(true, ExtensionHelper::callService($service, 'apiInfo'));

            case 'proxies':
                return $this->reply(true, ExtensionHelper::callService($service, 'apiProxies'));

            case 'rotate':
                if (!$this->truthy($settings['allow_rotation'] ?? false)) {
                    return $this->reply(false, 'You are not allowed to rotate!');
                }

                $max = $this->property($service, 'proxy_max_rotate');
                $used = (int) $this->property($service, 'proxy_rotation_counter');

                if ($max !== null && ctype_digit((string) $max) && (int) $max > 0 && $used >= (int) $max) {
                    return $this->reply(false, 'Rotation limit exceeded, max(' . $max . ')');
                }

                ExtensionHelper::callService($service, 'rotate');

                return $this->reply(true, 'success');

            case 'setrotate':
                $minutes = $request->input('minutes');

                if (ctype_digit((string) $minutes)) {
                    if ((int) $minutes < 0) {
                        return $this->reply(false, 'Minutes must be greater or equal than 0');
                    }

                    // 10800, which is what the module checked — not the 10080 its own form
                    // used. The script a customer already has was written against this.
                    if ((int) $minutes > 10800) {
                        return $this->reply(false, 'Minutes can\'t must be greater or equal than 10800');
                    }
                } elseif ($minutes !== 'null') {
                    return $this->reply(false, 'Rotation must be a number or "null"');
                }

                ExtensionHelper::callService($service, 'clientUpdateRotation', [(int) $minutes]);

                return $this->reply(true, 'success');

            case 'authip':
                $allowed = (int) ($settings['auth_ips'] ?? 0);

                if ($allowed <= 0) {
                    return $this->reply(false, 'Auth_ips disabled');
                }

                $ips = [];

                foreach ((array) $request->input('ips', []) as $ip) {
                    $ip = trim((string) $ip);

                    if ($ip === '') {
                        continue;
                    }

                    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        return $this->reply(false, 'Unable to validate ip address');
                    }

                    $ips[] = $ip;
                }

                if (count($ips) > $allowed) {
                    return $this->reply(false, 'You cant have more than ' . $allowed . ' amount of auth_ips');
                }

                ExtensionHelper::callService($service, 'clientUpdateAuthIps', [$ips]);

                return $this->reply(true, 'success');

            case 'password':
                $password = (string) $request->input('password', '');

                if ($password === '' || strlen($password) !== 8) {
                    return $this->reply(false, 'Password is mandatory, password should contains a-zA-Z0-9 and must be 8 chars long');
                }

                ExtensionHelper::callService($service, 'clientUpdatePassword', [$password]);

                return $this->reply(true, 'success');

            case 'reboot':
                ExtensionHelper::callService($service, 'reboot');

                return $this->reply(true, 'success');

            case 'reboot_hard':
                // reboot() takes the hard flag; there is no separate method.
                ExtensionHelper::callService($service, 'reboot', [true]);

                return $this->reply(true, 'success');

            default:
                return $this->reply(false, 'Action not found');
        }
    }

    /**
     * The product's module settings, which gate rotation and the authorized-IP count.
     *
     * core's own helper, not a hand-rolled loop: these rows are keyed by `key`, and reading
     * `name` instead quietly produced an empty array — rotation would have refused every
     * caller and authorized IPs would have reported themselves disabled.
     */
    private function settings(Service $service): array
    {
        return ExtensionHelper::settingsToArray($service->product->settings);
    }

    private function property(Service $service, string $key): ?string
    {
        return $service->properties()->where('key', $key)->first()?->value;
    }

    /** Constant time, so the endpoint gives nothing away about a near-miss key. */
    private function keyMatches(Service $service, string $key): bool
    {
        $stored = $this->property($service, 'proxy_api_key');

        return $stored !== null && $stored !== '' && hash_equals($stored, $key);
    }

    private function truthy($value): bool
    {
        return in_array($value, [true, 1, '1', 'yes', 'on', 'true'], true);
    }
}
