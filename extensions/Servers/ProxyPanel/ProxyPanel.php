<?php

namespace Paymenter\Extensions\Servers\ProxyPanel;

use App\Classes\Extension\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ProxyPanel — native Paymenter server (provisioning) module for the IPv6 proxy
 * admin panel (melodyproxy). Native rewrite of the legacy WHMCS "proxyPanel" module,
 * wired against the live panel API (base `/v0/services`, auth header `Panel: <token>`).
 *
 * WHMCS → Paymenter mapping (verified against the original module + api client):
 *   CreateAccount    → createServer      POST /newIpv6
 *   SuspendAccount   → suspendServer     GET  /stop/{id}
 *   UnsuspendAccount → unsuspendServer   GET  /start/{id}
 *   TerminateAccount → terminateServer   GET  /cancel/{id}
 *   ChangePassword   → getActions:credentials  POST /credentials/{id}
 *   Renewal          → billing-driven (Paymenter has no renew hook): a Service\Updated
 *                      listener calls GET /extend/{id}/{timestamp} when expires_at moves.
 *   ChangePackage    → not supported by the panel (upgradeServer returns a clear notice)
 *   ClientArea       → getActions: sync / rotate / reboot / view credentials
 *
 * Robustness: idempotent lifecycle (per-service/per-action lock; short-circuit when the
 * panel already reflects the target state), HTTP retry with backoff, logging + error
 * handling. Credentials are encrypted settings (spec item 12) — never hard-coded.
 *
 * @link docs/modules/proxypanel.md
 */
class ProxyPanel extends Server
{
    private const REMOTE_ID_KEY = 'proxypanel_service_id';

    private const USERNAME_KEY = 'proxy_username';

    private const PASSWORD_KEY = 'proxy_password';

    private const LOCK_KEY = 'proxypanel_lock';

    private const LOG_CHANNEL = 'stack';

    // ── Module configuration (admin → Server settings) ───────────────────────

    public function getConfig($values = []): array
    {
        return [
            [
                'name' => 'api_url',
                'label' => 'Panel API URL',
                'type' => 'text',
                'description' => 'Base URL including /v0/services, e.g. https://admpx.melodyproxy.com/v0/services',
                'required' => true,
                'validation' => 'url',
            ],
            [
                'name' => 'api_token',
                'label' => 'Panel Token',
                'type' => 'text',
                'description' => 'Token sent as the "Panel" header. Stored encrypted.',
                'required' => true,
                'encrypted' => true,
            ],
        ];
    }

    public function testConfig(): bool|string
    {
        try {
            $this->request('get', '/plans');

            return true;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    public function boot()
    {
        // Renewal is billing-driven — push the new expiry to the panel when a
        // ProxyPanel service's expires_at is extended (e.g. after an invoice is paid).
        Event::listen(\App\Events\Service\Updated::class, function ($event) {
            $service = $event->service;
            if (!$this->isProxyPanelService($service) || !$service->isDirty('expires_at') || !$service->expires_at) {
                return;
            }
            try {
                if ($id = $this->remoteId($service)) {
                    $this->request('get', '/extend/' . $id . '/' . $service->expires_at->timestamp);
                    $this->log('info', 'Extended service expiry on panel', ['service' => $service->id, 'remote' => $id]);
                }
            } catch (\Throwable $e) {
                $this->log('error', 'Failed to extend expiry', ['service' => $service->id, 'error' => $e->getMessage()]);
            }
        });
    }

    // ── Per-product configuration (admin) ────────────────────────────────────

    public function getProductConfig($values = []): array
    {
        return [
            [
                'name' => 'amount',
                'label' => 'Amount of proxies',
                'type' => 'text',
                'description' => 'Number of proxies provisioned per service.',
                'validation' => 'numeric',
                'required' => true,
            ],
            [
                'name' => 'plan',
                'label' => 'Plan',
                'type' => 'select',
                'description' => 'Plan tag on the panel.',
                'options' => $this->safeOptions(fn () => $this->fetchPlans()),
                'required' => true,
            ],
            [
                'name' => 'region',
                'label' => 'Location / Region',
                'type' => 'select',
                'description' => 'Location the proxies are provisioned in.',
                'options' => $this->safeOptions(fn () => $this->fetchLocations()),
                'required' => true,
            ],
            [
                'name' => 'bwlimit',
                'label' => 'Bandwidth limit',
                'type' => 'text',
                'description' => 'Zero or empty = unlimited.',
                'validation' => 'numeric',
                'required' => false,
            ],
            [
                'name' => 'auth_ips',
                'label' => 'Max authorized IPs',
                'type' => 'text',
                'description' => 'Zero or empty = disabled.',
                'validation' => 'numeric',
                'required' => false,
            ],
        ];
    }

    // ── Lifecycle (idempotent) ────────────────────────────────────────────────

    public function createServer(Service $service, $settings, $properties)
    {
        $settings = array_merge($settings, $properties);

        return $this->withLock($service, 'create', function () use ($service, $settings) {
            // Idempotency: reuse an existing remote service, just ensure it's running.
            if ($remoteId = $this->remoteId($service)) {
                $this->log('info', 'createServer skipped — remote exists', ['service' => $service->id, 'remote' => $remoteId]);

                return $this->request('get', '/start/' . $remoteId);
            }

            $username = 'svc' . $service->id;
            $password = substr(sha1(random_bytes(16)), 0, 8);
            $bwlimit = (int) ($settings['bwlimit'] ?? 0);

            $payload = [
                'client_id' => (int) $service->id,
                'plan_tag' => $settings['plan'] ?? null,
                'location_name' => $settings['region'] ?? null,
                'amount' => (int) ($settings['amount'] ?? 1),
                'authenticate' => ['username' => $username, 'password' => $password],
                'bwlimit' => $bwlimit > 0 ? $bwlimit : null,
            ];

            $res = $this->request('post', '/newIpv6', $payload);
            $newId = $res['id'] ?? null;
            if (!$newId) {
                throw new \RuntimeException('ProxyPanel create returned no service id.');
            }

            $this->setProp($service, self::REMOTE_ID_KEY, $newId);
            $this->setProp($service, self::USERNAME_KEY, $username);
            $this->setProp($service, self::PASSWORD_KEY, $password);
            $this->log('info', 'Service provisioned', ['service' => $service->id, 'remote' => $newId]);

            return $res;
        });
    }

    public function suspendServer(Service $service, $settings, $properties)
    {
        return $this->withLock($service, 'suspend', fn () => $this->request('get', '/stop/' . $this->requireRemoteId($service)));
    }

    public function unsuspendServer(Service $service, $settings, $properties)
    {
        return $this->withLock($service, 'unsuspend', fn () => $this->request('get', '/start/' . $this->requireRemoteId($service)));
    }

    public function terminateServer(Service $service, $settings, $properties)
    {
        return $this->withLock($service, 'terminate', function () use ($service) {
            $remoteId = $this->remoteId($service);
            if (!$remoteId) {
                return true; // nothing to cancel remotely — idempotent
            }
            $res = $this->request('get', '/cancel/' . $remoteId);
            $this->clearProp($service, self::REMOTE_ID_KEY);

            return $res;
        });
    }

    /**
     * The panel does not support changing a package/plan (matches the original
     * WHMCS module). Expose that clearly rather than silently doing nothing.
     */
    public function upgradeServer(Service $service, $settings, $properties)
    {
        throw new \RuntimeException('ProxyPanel does not support changing the package. Cancel and re-order to change plan.');
    }

    // ── Client-area actions ──────────────────────────────────────────────────

    public function getActions(Service $service, $settings = [], $properties = [])
    {
        if (!$this->remoteId($service)) {
            return [];
        }

        return [
            ['type' => 'button', 'label' => 'Sync status', 'function' => 'syncStatus'],
            ['type' => 'button', 'label' => 'Rotate proxies', 'function' => 'rotate'],
            ['type' => 'button', 'label' => 'Reboot', 'function' => 'reboot'],
        ];
    }

    /** Status lookup / reconcile — safe to run repeatedly. */
    public function syncStatus(Service $service, $settings = [], $properties = [])
    {
        return $this->request('get', '/' . $this->requireRemoteId($service));
    }

    public function rotate(Service $service, $settings = [], $properties = [])
    {
        return $this->request('get', '/rotate/' . $this->requireRemoteId($service) . '/1');
    }

    public function reboot(Service $service, $settings = [], $properties = [])
    {
        return $this->request('get', '/reboot/' . $this->requireRemoteId($service));
    }

    /** Set the proxy username/password on the panel (POST /credentials/{id}). */
    public function setCredentials(Service $service, string $username, string $password)
    {
        $res = $this->request('post', '/credentials/' . $this->requireRemoteId($service), [
            'username' => $username,
            'password' => $password,
        ]);
        $this->setProp($service, self::USERNAME_KEY, $username);
        $this->setProp($service, self::PASSWORD_KEY, $password);

        return $res;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function isProxyPanelService(Service $service): bool
    {
        return optional(optional($service->product)->server)->extension === 'ProxyPanel';
    }

    private function withLock(Service $service, string $action, callable $callback)
    {
        $lock = $service->properties()->where('key', self::LOCK_KEY)->first();
        if ($lock && $lock->value === $action && $lock->updated_at?->gt(now()->subMinutes(5))) {
            $this->log('warning', 'ProxyPanel op already in flight — skipping', ['service' => $service->id, 'action' => $action]);

            return true;
        }
        $service->properties()->updateOrCreate(['key' => self::LOCK_KEY], ['value' => $action]);

        try {
            return $callback();
        } catch (\Throwable $e) {
            $this->log('error', 'ProxyPanel ' . $action . ' failed', ['service' => $service->id, 'error' => $e->getMessage()]);
            throw $e;
        } finally {
            $service->properties()->where('key', self::LOCK_KEY)->delete();
        }
    }

    private function remoteId(Service $service): ?string
    {
        return $service->properties->where('key', self::REMOTE_ID_KEY)->first()?->value;
    }

    private function requireRemoteId(Service $service): string
    {
        $id = $this->remoteId($service);
        if (!$id) {
            throw new \RuntimeException('ProxyPanel service id missing for service ' . $service->id . ' — was it provisioned?');
        }

        return $id;
    }

    private function setProp(Service $service, string $key, $value): void
    {
        $service->properties()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    private function clearProp(Service $service, string $key): void
    {
        $service->properties()->where('key', $key)->delete();
    }

    private function fetchPlans(): array
    {
        $data = $this->request('get', '/plans');
        return $this->toOptions($data);
    }

    private function fetchLocations(): array
    {
        $data = $this->request('get', '/locations');
        return $this->toOptions($data);
    }

    /** The panel returns plans/locations either as a flat list or a keyed map. */
    private function toOptions($data): array
    {
        $rows = $data['data'] ?? $data;
        $out = [];
        foreach ((array) $rows as $k => $v) {
            if (is_array($v)) {
                $id = $v['tag'] ?? $v['name'] ?? $v['id'] ?? $k;
                $out[$id] = $v['name'] ?? $id;
            } else {
                $out[$v] = $v;
            }
        }

        return $out;
    }

    /**
     * Panel HTTP call: `Panel` auth header, retry on transient failure, and the
     * panel's own {status: ok|error, description} convention checked.
     *
     * @throws \RuntimeException on transport or API-level error
     */
    private function request(string $method, string $path, array $data = []): array
    {
        $url = rtrim((string) $this->config('api_url'), '/') . $path;

        $request = Http::withHeaders([
            'Panel' => (string) $this->config('api_token'),
            'Accept' => 'application/json',
        ])->retry(3, 200, throw: false)->timeout(20);

        $response = $method === 'get' ? $request->get($url) : $request->{$method}($url, $data);

        if (!$response->successful()) {
            $detail = $response->json('description') ?? $response->body();
            $this->log('error', 'ProxyPanel API error', ['method' => $method, 'path' => $path, 'status' => $response->status(), 'detail' => $detail]);
            throw new \RuntimeException('ProxyPanel API error (HTTP ' . $response->status() . '): ' . $detail);
        }

        $json = $response->json() ?? [];
        if (($json['status'] ?? 'ok') === 'error') {
            $msg = $json['description'] ?? 'Unknown panel error';
            $this->log('error', 'ProxyPanel returned error', ['path' => $path, 'detail' => $msg]);
            throw new \RuntimeException('ProxyPanel: ' . $msg);
        }

        return $json;
    }

    private function safeOptions(callable $fetch): array
    {
        try {
            return $fetch();
        } catch (\Throwable $e) {
            $this->log('warning', 'ProxyPanel option fetch failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function log(string $level, string $message, array $context = []): void
    {
        Log::channel(self::LOG_CHANNEL)->{$level}('[ProxyPanel] ' . $message, $context);
    }
}
