<?php

namespace Paymenter\Extensions\Servers\ProxyPanel;

use App\Classes\Extension\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Paymenter\Extensions\Others\ProvisioningOps\ProvisioningOps;

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

    // Cached panel state, refreshed by syncStatus() and by the panel callback, so the
    // client area can show live details without an API call on every page render.
    private const STATUS_KEY = 'proxy_status';

    private const IPS_KEY = 'proxy_ips';

    private const HOST_KEY = 'proxy_host';

    private const SYNCED_KEY = 'proxy_synced_at';

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
            [
                'name' => 'callback_secret',
                'label' => 'Callback Secret',
                'type' => 'text',
                'description' => 'Shared secret the panel must send when it calls back into Paymenter, '
                    . 'either as the "X-Panel-Secret" header or as an HMAC-SHA256 of the raw body in '
                    . '"X-Panel-Signature". Leave empty to disable the callback endpoint entirely. '
                    . 'Stored encrypted.',
                'required' => false,
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
        require __DIR__ . '/routes.php';

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

            // Create returns the allocated addresses — cache them so the customer sees
            // their proxies immediately, without waiting for a sync.
            $this->cachePanelState($service, $res);

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

    /**
     * What the customer sees on the service page.
     *
     * `text` entries render as labelled fields, `button` entries as actions. Values come
     * from cached service properties (written by createServer, syncStatus and the panel
     * callback) so opening the page never blocks on the panel API.
     */
    public function getActions(Service $service, $settings = [], $properties = [])
    {
        if (!$this->remoteId($service)) {
            return [];
        }

        $actions = [];

        foreach ($this->customerFields($service) as $label => $value) {
            if ($value !== null && $value !== '') {
                $actions[] = ['type' => 'text', 'label' => $label, 'text' => $value];
            }
        }

        $actions[] = ['type' => 'button', 'label' => 'Sync status', 'function' => 'syncStatus'];
        $actions[] = ['type' => 'button', 'label' => 'Rotate proxies', 'function' => 'rotate'];
        $actions[] = ['type' => 'button', 'label' => 'Reboot', 'function' => 'reboot'];

        return $actions;
    }

    /**
     * The provisioned proxy details shown to the customer.
     *
     * Labels are translated through the `proxypanel` lang file so the client can reword
     * them without touching code.
     */
    private function customerFields(Service $service): array
    {
        $prop = fn (string $key) => $service->properties->where('key', $key)->first()?->value;

        $synced = $prop(self::SYNCED_KEY);

        return [
            __('proxypanel.proxy_username') => $prop(self::USERNAME_KEY),
            __('proxypanel.proxy_password') => $prop(self::PASSWORD_KEY),
            __('proxypanel.proxy_endpoints') => $prop(self::IPS_KEY),
            __('proxypanel.proxy_host') => $prop(self::HOST_KEY),
            __('proxypanel.panel_status') => $prop(self::STATUS_KEY),
            __('proxypanel.panel_service_id') => $prop(self::REMOTE_ID_KEY),
            __('proxypanel.last_synced') => $synced ? \Carbon\Carbon::parse($synced)->diffForHumans() : null,
        ];
    }

    /** Status lookup / reconcile — safe to run repeatedly. */
    public function syncStatus(Service $service, $settings = [], $properties = [])
    {
        $data = $this->request('get', '/' . $this->requireRemoteId($service));

        $this->cachePanelState($service, $data);

        return $data;
    }

    /**
     * Persist the panel's view of a service onto the Paymenter service, so the client
     * area and the admin both show real data without another API round-trip.
     */
    private function cachePanelState(Service $service, array $data): void
    {
        $payload = $data['data'] ?? $data;

        if (!is_array($payload)) {
            return;
        }

        if ($status = $payload['status'] ?? $payload['state'] ?? null) {
            // The envelope's own ok/error `status` is not the service state.
            if (!in_array($status, ['ok', 'error'], true)) {
                $this->setProp($service, self::STATUS_KEY, (string) $status);
            }
        }

        $ips = $payload['ips'] ?? $payload['proxies'] ?? $payload['ipv6'] ?? null;
        if (is_array($ips)) {
            $flat = [];
            foreach ($ips as $ip) {
                $flat[] = is_array($ip) ? ($ip['ip'] ?? $ip['address'] ?? json_encode($ip)) : (string) $ip;
            }
            $this->setProp($service, self::IPS_KEY, implode(', ', array_filter($flat)));
        } elseif (is_string($ips) && $ips !== '') {
            $this->setProp($service, self::IPS_KEY, $ips);
        }

        if ($host = $payload['host'] ?? $payload['hostname'] ?? $payload['gateway'] ?? null) {
            $this->setProp($service, self::HOST_KEY, (string) $host);
        }

        $this->setProp($service, self::SYNCED_KEY, now()->toDateTimeString());
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

    // ── Panel callback ───────────────────────────────────────────────────────

    /**
     * Handle a status callback from the proxy panel.
     *
     * Authentication (either is accepted, both are constant-time compared):
     *   - `X-Panel-Secret: <callback_secret>`, or
     *   - `X-Panel-Signature: <hex HMAC-SHA256 of the raw body, keyed with callback_secret>`
     *
     * Body (JSON). The service is resolved by whichever identifier the panel sends:
     *   - `service_id`     → the Paymenter service id (what we send as `client_id`), or
     *   - `id` / `panel_id` → the panel's own service id (stored as proxypanel_service_id)
     *
     * Recognised state fields: `status` or `event`. Everything else in the body is
     * cached onto the service (ips, host, …) and echoed into the log.
     *
     * NOTE: the panel's real callback contract is still an open question for the client
     * (see docs/modules/proxypanel.md § Open questions). This handler is deliberately
     * tolerant about field names and *never* guesses a state it does not recognise — an
     * unknown state is logged and acknowledged, not applied.
     */
    public function callback(\Illuminate\Http\Request $request)
    {
        $secret = (string) $this->config('callback_secret');

        if ($secret === '') {
            $this->log('warning', 'Callback received but no callback secret is configured — rejected');

            return response()->json(['status' => 'error', 'description' => 'Callbacks are not enabled'], 403);
        }

        if (!$this->isValidCallback($request, $secret)) {
            $this->log('warning', 'Callback rejected: bad secret/signature', ['ip' => $request->ip()]);

            return response()->json(['status' => 'error', 'description' => 'Invalid signature'], 401);
        }

        $payload = $request->json()->all() ?: $request->all();
        $service = $this->resolveServiceFromCallback($payload);

        if (!$service) {
            $this->log('warning', 'Callback for unknown service', ['payload' => $payload]);

            // Acknowledge so the panel stops retrying something we can never resolve.
            return response()->json(['status' => 'ok', 'description' => 'Unknown service, ignored']);
        }

        $this->cachePanelState($service, $payload);

        $state = strtolower((string) ($payload['event'] ?? $payload['status'] ?? ''));
        $applied = $this->applyCallbackState($service, $state);

        $this->log('info', 'Callback processed', [
            'service' => $service->id,
            'state' => $state,
            'applied' => $applied ?? 'none',
        ]);

        if ($applied === null && $state !== '') {
            // Unrecognised state: record it so the admin sees the panel is sending
            // something we do not map yet, rather than silently dropping it.
            ProvisioningOps::failed(
                $service,
                'ProxyPanel',
                'callback',
                new \RuntimeException('Unrecognised callback state: "' . $state . '"'),
                ['payload' => $payload],
            );
        }

        return response()->json(['status' => 'ok']);
    }

    private function isValidCallback(\Illuminate\Http\Request $request, string $secret): bool
    {
        if ($header = $request->header('X-Panel-Secret')) {
            return hash_equals($secret, (string) $header);
        }

        if ($signature = $request->header('X-Panel-Signature')) {
            $expected = hash_hmac('sha256', $request->getContent(), $secret);

            return hash_equals($expected, (string) $signature);
        }

        return false;
    }

    private function resolveServiceFromCallback(array $payload): ?Service
    {
        if ($id = $payload['service_id'] ?? $payload['client_id'] ?? null) {
            $service = Service::find($id);
            if ($service && $this->isProxyPanelService($service)) {
                return $service;
            }
        }

        $remoteId = $payload['id'] ?? $payload['panel_id'] ?? null;
        if ($remoteId === null) {
            return null;
        }

        // Service properties are a polymorphic `properties` table (model_type/model_id).
        $property = \App\Models\Property::where('key', self::REMOTE_ID_KEY)
            ->where('value', (string) $remoteId)
            ->where('model_type', Service::class)
            ->first();

        return $property ? Service::find($property->model_id) : null;
    }

    /**
     * Map a panel state onto the Paymenter service status.
     *
     * Returns the status applied, or null when the state is not recognised. Idempotent:
     * re-delivering the same callback is a no-op.
     */
    private function applyCallbackState(Service $service, string $state): ?string
    {
        $target = match ($state) {
            'active', 'running', 'started', 'online', 'created', 'unsuspended' => Service::STATUS_ACTIVE,
            'suspended', 'stopped', 'paused', 'offline' => Service::STATUS_SUSPENDED,
            'cancelled', 'canceled', 'terminated', 'deleted', 'destroyed' => Service::STATUS_CANCELLED,
            default => null,
        };

        if ($target === null) {
            return null;
        }

        if ($service->status !== $target) {
            $service->status = $target;
            $service->save();
        }

        // A successful create callback closes any recorded provisioning failure.
        if ($target === Service::STATUS_ACTIVE) {
            ProvisioningOps::succeeded($service, 'ProxyPanel', 'create', ['via' => 'callback']);
        }

        return $target;
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
            $result = $callback();

            // Closes any earlier failure for this action in the admin Provisioning list.
            ProvisioningOps::succeeded($service, 'ProxyPanel', $action);

            return $result;
        } catch (\Throwable $e) {
            $this->log('error', 'ProxyPanel ' . $action . ' failed', ['service' => $service->id, 'error' => $e->getMessage()]);

            // Make the failure visible + retryable in the admin, and stop a failed
            // create from leaving the order silently "active".
            ProvisioningOps::failed($service, 'ProxyPanel', $action, $e, [
                'api_url' => (string) $this->config('api_url'),
            ]);

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
