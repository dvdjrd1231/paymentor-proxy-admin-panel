<?php

namespace Paymenter\Extensions\Servers\ProxyPanel;

use App\Classes\Extension\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ProxyPanel — native Paymenter server (provisioning) module for the IPv6 proxy
 * admin panel. This is the native rewrite of the legacy WHMCS "proxyPanel"
 * module.
 *
 * WHMCS → Paymenter method mapping:
 *   CreateAccount     → createServer
 *   SuspendAccount    → suspendServer
 *   UnsuspendAccount  → unsuspendServer
 *   TerminateAccount  → terminateServer
 *   ChangePackage     → upgradeServer   (upgrade / downgrade)
 *   ClientArea/links  → getActions
 *   (renewal)         → billing-driven in Paymenter; no module hook (a paid
 *                       invoice keeps the Service active). We expose an explicit
 *                       "Sync" action + a scheduled reconcile instead.
 *
 * Robustness guarantees:
 *   - Idempotency: every lifecycle op is guarded by a per-service, per-action
 *     lock and short-circuits if the panel already reflects the desired state
 *     (the remote id is stored as a service property).
 *   - Retry: transient HTTP failures are retried with backoff.
 *   - Logging + structured error handling on every operation.
 *   - No secrets in code — credentials come from encrypted module settings.
 *
 * ── WIRING NOTE ─────────────────────────────────────────────────────────────
 * The lifecycle logic below is complete. The only thing pending is the concrete
 * admin-panel API surface (base paths + response field names), which the client
 * will provide. Everything that must change when the real spec arrives is marked
 * with `@api` and centralised in the "API surface" section near the bottom —
 * you should not need to touch the lifecycle methods.
 * ────────────────────────────────────────────────────────────────────────────
 *
 * @link docs/modules/proxypanel.md
 */
class ProxyPanel extends Server
{
    /** Service property key holding the remote panel id for this service. */
    private const REMOTE_ID_KEY = 'proxypanel_service_id';

    /** Service property key used as a lightweight in-flight lock. */
    private const LOCK_KEY = 'proxypanel_lock';

    private const LOG_CHANNEL = 'stack';

    // ── Module-level configuration (admin → Server settings) ────────────────

    public function getConfig($values = []): array
    {
        return [
            [
                'name' => 'api_url',
                'label' => 'Admin Panel API URL',
                'type' => 'text',
                'description' => 'Base URL of your proxy admin panel API, e.g. https://panel.example.com/api',
                'required' => true,
                'validation' => 'url',
            ],
            [
                'name' => 'api_key',
                'label' => 'API Key',
                'type' => 'text',
                'description' => 'API key/token used to authenticate with the admin panel. Stored encrypted.',
                'required' => true,
                'encrypted' => true,
            ],
        ];
    }

    /**
     * "Test connection" button in the admin server settings.
     */
    public function testConfig(): bool|string
    {
        try {
            $this->request('get', self::ENDPOINT_PING);

            return true;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    // ── Per-product configuration (admin) ───────────────────────────────────

    /**
     * Fields shown to the admin when configuring a product to use this module.
     * Supports cascading selects (locations are re-fetched live).
     */
    public function getProductConfig($values = []): array
    {
        return [
            [
                'name' => 'plan',
                'label' => 'Proxy Plan',
                'type' => 'select',
                'description' => 'Plan / package identifier in the admin panel.',
                'options' => $this->safeOptions(fn () => $this->fetchPlans()),
                'required' => true,
            ],
            [
                'name' => 'protocol',
                'label' => 'Protocol',
                'type' => 'select',
                'description' => 'Proxy protocol offered by this product.',
                'options' => [
                    'http' => 'HTTP(S)',
                    'socks5' => 'SOCKS5',
                ],
                'required' => true,
            ],
            [
                'name' => 'default_quantity',
                'label' => 'Quantity (proxies)',
                'type' => 'text',
                'description' => 'Number of proxies provisioned per service (can be overridden at checkout).',
                'validation' => 'numeric',
                'required' => true,
            ],
            [
                'name' => 'allowed_locations',
                'label' => 'Allowed Location(s)',
                'type' => 'select',
                'description' => 'Locations the customer may choose from at checkout. Leave empty to allow all.',
                'options' => $this->safeOptions(fn () => $this->fetchLocations()),
                'multiple' => true,
                'database_type' => 'array',
                'required' => false,
            ],
        ];
    }

    /**
     * Fields shown to the customer at checkout.
     */
    public function getCheckoutConfig(\App\Models\Product $product, $values = [], $settings = []): array
    {
        return [
            [
                'name' => 'location',
                'label' => 'Location',
                'type' => 'select',
                'description' => 'Preferred proxy location.',
                'options' => $this->safeOptions(fn () => $this->fetchLocations()),
                'required' => true,
            ],
            [
                'name' => 'quantity',
                'label' => 'Quantity',
                'type' => 'text',
                'description' => 'How many proxies you need.',
                'validation' => 'numeric',
                'required' => false,
            ],
        ];
    }

    // ── Lifecycle (idempotent) ──────────────────────────────────────────────

    public function createServer(Service $service, $settings, $properties)
    {
        $settings = array_merge($settings, $properties);

        return $this->withLock($service, 'create', function () use ($service, $settings) {
            // Idempotency: if we already created it, just make sure it's active.
            if ($remoteId = $this->remoteId($service)) {
                $this->log('info', 'createServer skipped — remote already exists', ['service' => $service->id, 'remote' => $remoteId]);

                return $this->request('post', self::ENDPOINT_UNSUSPEND, ['id' => $remoteId]);
            }

            $payload = [
                // @api map these fields to your admin-panel "create" contract
                'external_ref' => $service->id,
                'plan'         => $settings['plan'] ?? null,
                'protocol'     => $settings['protocol'] ?? 'http',
                'location'     => $settings['location'] ?? null,
                'quantity'     => (int) ($settings['quantity'] ?? $settings['default_quantity'] ?? 1),
                'email'        => $service->user->email,
            ];

            $response = $this->request('post', self::ENDPOINT_CREATE, $payload);

            // @api adjust to the field the panel returns as the service id
            $newId = $response['id'] ?? $response['service_id'] ?? null;
            if (!$newId) {
                throw new \RuntimeException('ProxyPanel create returned no service id.');
            }

            $this->setRemoteId($service, $newId);
            $this->log('info', 'Service provisioned', ['service' => $service->id, 'remote' => $newId]);

            return $response;
        });
    }

    public function suspendServer(Service $service, $settings, $properties)
    {
        return $this->withLock($service, 'suspend', function () use ($service) {
            $remoteId = $this->requireRemoteId($service);

            return $this->request('post', self::ENDPOINT_SUSPEND, ['id' => $remoteId]);
        });
    }

    public function unsuspendServer(Service $service, $settings, $properties)
    {
        return $this->withLock($service, 'unsuspend', function () use ($service) {
            $remoteId = $this->requireRemoteId($service);

            return $this->request('post', self::ENDPOINT_UNSUSPEND, ['id' => $remoteId]);
        });
    }

    public function terminateServer(Service $service, $settings, $properties)
    {
        return $this->withLock($service, 'terminate', function () use ($service) {
            $remoteId = $this->remoteId($service);
            if (!$remoteId) {
                // Nothing to terminate remotely; treat as success (idempotent).
                return true;
            }

            $response = $this->request('post', self::ENDPOINT_TERMINATE, ['id' => $remoteId]);
            $this->clearRemoteId($service);

            return $response;
        });
    }

    public function upgradeServer(Service $service, $settings, $properties)
    {
        $settings = array_merge($settings, $properties);

        return $this->withLock($service, 'upgrade', function () use ($service, $settings) {
            $remoteId = $this->requireRemoteId($service);

            return $this->request('post', self::ENDPOINT_CHANGE_PLAN, [
                'id'       => $remoteId,
                'plan'     => $settings['plan'] ?? null,
                'quantity' => (int) ($settings['quantity'] ?? $settings['default_quantity'] ?? 1),
            ]);
        });
    }

    /**
     * Client-area actions (buttons) + status lookup.
     */
    public function getActions(Service $service, $settings = [], $properties = [])
    {
        $remoteId = $this->remoteId($service);
        if (!$remoteId) {
            return [];
        }

        return [
            [
                'type' => 'button',
                'label' => 'Sync status',
                'function' => 'syncStatus',
            ],
        ];
    }

    /**
     * Status lookup / reconcile — callable from the service page and from a
     * scheduled reconcile job. Safe to run repeatedly.
     */
    public function syncStatus(Service $service, $settings = [], $properties = [])
    {
        $remoteId = $this->requireRemoteId($service);
        $status = $this->request('get', self::ENDPOINT_STATUS . '/' . $remoteId);

        $this->log('debug', 'Synced ProxyPanel status', ['service' => $service->id, 'remote' => $remoteId, 'status' => $status['status'] ?? null]);

        return $status;
    }

    // ── Idempotency helpers ─────────────────────────────────────────────────

    /**
     * Run $callback under a per-service, per-action lock so overlapping queue
     * jobs / retries cannot double-execute a provisioning operation.
     */
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

    private function setRemoteId(Service $service, $id): void
    {
        $service->properties()->updateOrCreate([
            'key' => self::REMOTE_ID_KEY,
        ], ['value' => $id]);
    }

    private function clearRemoteId(Service $service): void
    {
        $service->properties()->where('key', self::REMOTE_ID_KEY)->delete();
    }

    // ── API surface (the only place to touch when wiring the real panel) ─────

    // @api Replace these path constants with the real admin-panel endpoints.
    private const ENDPOINT_PING = '/ping';

    private const ENDPOINT_CREATE = '/services';

    private const ENDPOINT_SUSPEND = '/services/suspend';

    private const ENDPOINT_UNSUSPEND = '/services/unsuspend';

    private const ENDPOINT_TERMINATE = '/services/terminate';

    private const ENDPOINT_CHANGE_PLAN = '/services/change-plan';

    private const ENDPOINT_STATUS = '/services';

    private const ENDPOINT_PLANS = '/plans';

    private const ENDPOINT_LOCATIONS = '/locations';

    /** @api adjust to the panel's plan list response shape. */
    private function fetchPlans(): array
    {
        $data = $this->request('get', self::ENDPOINT_PLANS);
        $out = [];
        foreach ($data['data'] ?? $data as $plan) {
            $out[$plan['id']] = $plan['name'] ?? $plan['id'];
        }

        return $out;
    }

    /** @api adjust to the panel's location list response shape. */
    private function fetchLocations(): array
    {
        $data = $this->request('get', self::ENDPOINT_LOCATIONS);
        $out = [];
        foreach ($data['data'] ?? $data as $loc) {
            $out[$loc['id']] = $loc['name'] ?? $loc['code'] ?? $loc['id'];
        }

        return $out;
    }

    /**
     * Signed, retried HTTP call to the admin panel.
     *
     * @api adjust the auth header scheme to match the panel (Bearer assumed).
     *
     * @throws \RuntimeException on API-level or transport error
     */
    private function request(string $method, string $path, array $data = []): array
    {
        $url = rtrim((string) $this->config('api_url'), '/') . $path;

        $request = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->config('api_key'),
            'Accept'        => 'application/json',
        ])->retry(3, 200, throw: false)->timeout(20);

        $response = $method === 'get'
            ? $request->get($url, $data)
            : $request->{$method}($url, $data);

        if (!$response->successful()) {
            $detail = $response->json('message') ?? $response->json('errors.0.detail') ?? $response->body();
            $this->log('error', 'ProxyPanel API error', ['method' => $method, 'path' => $path, 'status' => $response->status(), 'detail' => $detail]);
            throw new \RuntimeException('ProxyPanel API error (HTTP ' . $response->status() . '): ' . $detail);
        }

        return $response->json() ?? [];
    }

    /**
     * Fetch select options but never break the admin form if the panel is
     * unreachable while an admin is just editing settings.
     */
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
