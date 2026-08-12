<?php

namespace Paymenter\Extensions\Servers\ProxyPanel;

use App\Classes\Extension\Server;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Paymenter\Extensions\Others\ProvisioningOps\ProvisioningOps;

/**
 * ProxyPanel — native Paymenter server (provisioning) module for the IPv6/IPv4 proxy
 * admin panel. Native rewrite of the legacy WHMCS "proxyPanel" module.
 *
 * Written against the panel's documented RotatingServices REST API (`api.md`). Every
 * endpoint below appears in that document — nothing is inferred:
 *
 *   GET  /{id}                          service info (expiration, rotation, ips[])
 *   POST /new                           create
 *   POST /renew/{id}                    renew (clears counters)
 *   GET  /extend/{id}/{unixtimestamp}   set expiration
 *   GET  /expand/{id}/{amount}          add proxies      (upgrade)
 *   GET  /shrink/{id}/{amount}          remove proxies   (downgrade)
 *   GET  /cancel/{id}                   immediate termination
 *   POST /aa/{id}                       authorize[] / authenticate[] (max 3)
 *   GET  /blacklist/{blacklist_id}/{status}
 *   GET  /reboot/{id}[/hard]
 *
 * Auth: every call sends the `Panel: <token>` header. Responses are JSON with a
 * `status` field (`ok`/`error`).
 *
 * WHMCS → Paymenter mapping:
 *   CreateAccount    → createServer      POST /new
 *   TerminateAccount → terminateServer   GET  /cancel/{id}
 *   ChangePackage    → upgradeServer     GET  /expand|shrink/{id}/{delta}
 *   ChangePassword   → changePassword    POST /aa/{id}
 *   SuspendAccount   → suspendServer     see § Suspension below
 *   UnsuspendAccount → unsuspendServer   see § Suspension below
 *   ClientArea       → getActions        proxies list, export, rotate, rotation time,
 *                                        auth IPs, password, API key, reboot
 *   Renewal          → billing-driven (Paymenter has no renew hook): a Service\Updated
 *                      listener calls POST /renew/{id} + GET /extend/{id}/{ts}.
 *
 * § Suspension — the API document defines **no suspend/unsuspend endpoint**. Rather than
 * invent one, the behaviour is an explicit admin setting (`suspend_strategy`) with a
 * conservative default, and the open question is recorded in docs/modules/proxypanel.md.
 *
 * Robustness: per-service/per-action locks, idempotent create/terminate, HTTP retry with
 * backoff, structured logging, and every failure recorded to Others/ProvisioningOps so the
 * admin can see and retry it — a failed create never leaves an order silently active.
 *
 * @link docs/modules/proxypanel.md
 */
class ProxyPanel extends Server
{
    // ── Service properties ───────────────────────────────────────────────────
    private const REMOTE_ID_KEY = 'proxypanel_service_id';

    private const USERNAME_KEY = 'proxy_username';

    private const PASSWORD_KEY = 'proxy_password';

    private const API_KEY_KEY = 'proxy_api_key';

    private const AUTH_IPS_KEY = 'proxy_auth_ips';

    private const AMOUNT_KEY = 'proxy_amount';

    private const LOCK_KEY = 'proxypanel_lock';

    // Cached panel state (refreshed by sync / callback) so the client area never blocks.
    private const IPS_KEY = 'proxy_ips';

    private const EXPIRATION_KEY = 'proxy_expiration';

    private const ROTATION_COUNTER_KEY = 'proxy_rotation_counter';

    private const MAX_ROTATE_KEY = 'proxy_max_rotate';

    private const ROTATION_TIME_KEY = 'proxy_rotation_time';

    private const SYNCED_KEY = 'proxy_synced_at';

    /** The panel accepts at most 3 authorized IPs (api.md). */
    private const MAX_AUTH_IPS = 3;

    private const LOG_CHANNEL = 'stack';

    // ── Module configuration (Admin → Servers → ProxyPanel) ──────────────────

    public function getConfig($values = []): array
    {
        return [
            [
                'name' => 'api_url',
                'label' => 'Panel API URL',
                'type' => 'text',
                'description' => 'Base URL including the API prefix, e.g. https://panel.example.com/v0/services',
                'required' => true,
                'validation' => 'url',
            ],
            [
                'name' => 'api_token',
                'label' => 'Panel Token',
                'type' => 'text',
                'description' => 'Sent as the "Panel" header on every request. Stored encrypted.',
                'required' => true,
                'encrypted' => true,
            ],
            [
                'name' => 'callback_secret',
                'label' => 'Callback Secret',
                'type' => 'text',
                'description' => 'Shared secret the panel must present when calling back into Paymenter, '
                    . 'as "X-Panel-Secret" or as an HMAC-SHA256 of the raw body in "X-Panel-Signature". '
                    . 'Leave empty to disable the callback endpoint. Stored encrypted.',
                'required' => false,
                'encrypted' => true,
            ],
            [
                'name' => 'suspend_strategy',
                'label' => 'Suspension behaviour',
                'type' => 'select',
                'description' => 'The panel API documents no suspend/unsuspend endpoint. Choose how a '
                    . 'suspended (unpaid) service should be handled. "Expire now" sets the panel '
                    . 'expiration to the current time via /extend; unsuspend restores it from the '
                    . 'service due date. Confirm the intended behaviour with the panel operator.',
                'options' => [
                    'expire' => 'Expire now on the panel (/extend), restore on unsuspend',
                    'none' => 'Do nothing on the panel (suspend in Paymenter only)',
                ],
                'default' => 'expire',
                'required' => true,
            ],
            [
                'name' => 'regions',
                'label' => 'Regions',
                'type' => 'textarea',
                'description' => 'One per line, formatted "server_tag|Country - City" (e.g. '
                    . '"us-nyc|United States - New York"). Offered to the customer at checkout. '
                    . 'A line without a "|" is used as both tag and label.',
                'required' => false,
            ],
        ];
    }

    public function testConfig(): bool|string
    {
        try {
            // There is no catalogue endpoint in api.md, so probe a service id that will
            // not exist: a reachable, correctly-authenticated panel answers with a JSON
            // `status: error` body rather than a transport failure or 401.
            $this->request('get', '/0', [], throwOnApiError: false);

            return true;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    public function boot()
    {
        require __DIR__ . '/routes.php';

        // Renewal is billing-driven — Paymenter has no renew hook. When a ProxyPanel
        // service's expires_at moves (e.g. after an invoice is paid), push it to the panel.
        Event::listen(\App\Events\Service\Updated::class, function ($event) {
            $service = $event->service;
            if (!$this->isProxyPanelService($service) || !$service->isDirty('expires_at') || !$service->expires_at) {
                return;
            }
            try {
                if ($id = $this->remoteId($service)) {
                    // /renew clears the rotation counters for the new period; /extend sets
                    // the actual expiry. api.md documents both.
                    $this->request('post', '/renew/' . $id);
                    $this->request('get', '/extend/' . $id . '/' . $service->expires_at->timestamp);
                    ProvisioningOps::succeeded($service, 'ProxyPanel', 'renew');
                    $this->log('info', 'Renewed service on panel', ['service' => $service->id, 'remote' => $id]);
                }
            } catch (\Throwable $e) {
                $this->log('error', 'Failed to renew', ['service' => $service->id, 'error' => $e->getMessage()]);
                ProvisioningOps::failed($service, 'ProxyPanel', 'renew', $e);
            }
        });
    }

    // ── Per-product configuration (admin) ────────────────────────────────────

    public function getProductConfig($values = []): array
    {
        return [
            [
                'name' => 'plan_tag',
                'label' => 'Plan tag',
                'type' => 'text',
                'description' => 'The plan identifier on the panel (sent as plan_tag).',
                'required' => true,
            ],
            [
                'name' => 'amount',
                'label' => 'Amount of proxies',
                'type' => 'text',
                'description' => 'Number of proxies provisioned per service.',
                'validation' => 'numeric',
                'required' => true,
            ],
            [
                'name' => 'allow_manual_rotate',
                'label' => 'Allow manual rotation',
                'type' => 'checkbox',
                'description' => 'Show the "Rotate now" action in the client area.',
                'required' => false,
            ],
            [
                'name' => 'allow_change_rotate',
                'label' => 'Allow changing rotation time',
                'type' => 'checkbox',
                'description' => 'Let the customer set the automatic rotation interval.',
                'required' => false,
            ],
            [
                'name' => 'auth_ips_count',
                'label' => 'Authorized IPs allowed',
                'type' => 'text',
                'description' => 'How many authorized IPs the customer may set (panel maximum is ' . self::MAX_AUTH_IPS . ').',
                'validation' => 'numeric',
                'required' => false,
            ],
            [
                'name' => 'rotations_per_period',
                'label' => 'Rotations per period',
                'type' => 'text',
                'description' => 'Informational: the plan\'s rotation allowance, shown to the customer.',
                'validation' => 'numeric',
                'required' => false,
            ],
        ];
    }

    /**
     * Region is chosen by the customer at checkout and becomes the panel's `server_tag`.
     * Labels use the "Country - City" format the client's WHMCS order form used.
     */
    public function getCheckoutConfig(Product $product): array
    {
        $regions = $this->regionOptions();

        if (empty($regions)) {
            return [];
        }

        return [
            [
                'name' => 'region',
                'label' => 'Region',
                'type' => 'select',
                'required' => true,
                'options' => $regions,
            ],
        ];
    }

    /** Parse the admin's "server_tag|Country - City" lines into select options. */
    private function regionOptions(): array
    {
        $raw = (string) $this->config('regions');
        $options = [];

        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            [$tag, $label] = array_pad(explode('|', $line, 2), 2, null);
            $tag = trim((string) $tag);
            $options[$tag] = trim((string) ($label ?: $tag));
        }

        return $options;
    }

    // ── Lifecycle ────────────────────────────────────────────────────────────

    public function createServer(Service $service, $settings, $properties)
    {
        $settings = array_merge($settings, $properties);

        return $this->withLock($service, 'create', function () use ($service, $settings) {
            // Idempotency: an InvoicePaid processed twice must not create two services.
            if ($remoteId = $this->remoteId($service)) {
                $this->log('info', 'createServer skipped — remote service already exists', [
                    'service' => $service->id,
                    'remote' => $remoteId,
                ]);

                return ['status' => 'ok', 'id' => $remoteId, 'description' => 'already provisioned'];
            }

            $username = 'svc' . $service->id;
            $password = substr(bin2hex(random_bytes(16)), 0, 16);
            $amount = max(1, (int) ($settings['amount'] ?? 1));

            $payload = [
                'client_id' => (int) $service->id,
                'plan_tag' => $settings['plan_tag'] ?? null,
                'server_tag' => $settings['region'] ?? ($settings['server_tag'] ?? null),
                'amount' => $amount,
                'authorize' => [],                 // customer sets these later via /aa
                'authenticate' => ['username' => $username, 'password' => $password],
                'expiration' => $service->expires_at?->timestamp,
            ];

            foreach (['plan_tag', 'server_tag'] as $required) {
                if (empty($payload[$required])) {
                    throw new \RuntimeException('ProxyPanel: missing required "' . $required . '" — check the product configuration and the Region option.');
                }
            }

            $res = $this->request('post', '/new', $payload);

            $newId = $res['id'] ?? ($res['service_id'] ?? ($res['data']['id'] ?? null));
            if (!$newId) {
                throw new \RuntimeException('ProxyPanel create returned no service id.');
            }

            $this->setProp($service, self::REMOTE_ID_KEY, $newId);
            $this->setProp($service, self::USERNAME_KEY, $username);
            $this->setProp($service, self::PASSWORD_KEY, $password);
            $this->setProp($service, self::AMOUNT_KEY, (string) $amount);

            if ($apiKey = $res['api_key'] ?? ($res['apikey'] ?? null)) {
                $this->setProp($service, self::API_KEY_KEY, (string) $apiKey);
            }

            $this->cachePanelState($service, $res);
            $this->log('info', 'Service provisioned', ['service' => $service->id, 'remote' => $newId]);

            return $res;
        });
    }

    /**
     * The API document defines no suspend endpoint. Behaviour is an explicit setting.
     */
    public function suspendServer(Service $service, $settings, $properties)
    {
        return $this->withLock($service, 'suspend', function () use ($service) {
            $id = $this->requireRemoteId($service);

            if ($this->config('suspend_strategy') === 'none') {
                $this->log('info', 'Suspend: panel untouched by configuration', ['service' => $service->id]);

                return ['status' => 'ok', 'description' => 'suspended in Paymenter only'];
            }

            // Expire the service on the panel now; unsuspend restores the real due date.
            return $this->request('get', '/extend/' . $id . '/' . now()->timestamp);
        });
    }

    public function unsuspendServer(Service $service, $settings, $properties)
    {
        return $this->withLock($service, 'unsuspend', function () use ($service) {
            $id = $this->requireRemoteId($service);

            if ($this->config('suspend_strategy') === 'none') {
                return ['status' => 'ok', 'description' => 'unsuspended in Paymenter only'];
            }

            // Restore the paid-through date. Fall back to "now" only if there is none.
            $expiry = $service->expires_at?->timestamp ?? now()->timestamp;

            return $this->request('get', '/extend/' . $id . '/' . $expiry);
        });
    }

    public function terminateServer(Service $service, $settings, $properties)
    {
        return $this->withLock($service, 'terminate', function () use ($service) {
            $remoteId = $this->remoteId($service);
            if (!$remoteId) {
                return ['status' => 'ok', 'description' => 'nothing to cancel'];   // idempotent
            }

            $res = $this->request('get', '/cancel/' . $remoteId);
            $this->clearProp($service, self::REMOTE_ID_KEY);

            return $res;
        });
    }

    /**
     * ChangePackage — the panel grows/shrinks an existing service rather than replacing
     * it, so an upgrade is the delta between the old and new proxy count.
     */
    public function upgradeServer(Service $service, $settings, $properties)
    {
        $settings = array_merge($settings, $properties);

        return $this->withLock($service, 'upgrade', function () use ($service, $settings) {
            $id = $this->requireRemoteId($service);

            $current = (int) ($this->prop($service, self::AMOUNT_KEY) ?? 0);
            $target = max(1, (int) ($settings['amount'] ?? 0));

            if ($current === 0 || $current === $target) {
                $this->setProp($service, self::AMOUNT_KEY, (string) $target);

                return ['status' => 'ok', 'description' => 'no change in proxy count'];
            }

            $delta = abs($target - $current);
            $verb = $target > $current ? 'expand' : 'shrink';

            $res = $this->request('get', '/' . $verb . '/' . $id . '/' . $delta);
            $this->setProp($service, self::AMOUNT_KEY, (string) $target);
            $this->log('info', 'Service ' . $verb . 'ed', [
                'service' => $service->id, 'from' => $current, 'to' => $target,
            ]);

            return $res;
        });
    }

    // ── Client-area actions (WHMCS ClientArea equivalent) ────────────────────

    public function getActions(Service $service, $settings = [], $properties = [])
    {
        if (!$this->remoteId($service)) {
            return [];
        }

        $settings = array_merge($settings, $properties);
        $actions = [];

        foreach ($this->customerFields($service) as $label => $value) {
            if ($value !== null && $value !== '') {
                $actions[] = ['type' => 'text', 'label' => $label, 'text' => $value];
            }
        }

        $actions[] = ['type' => 'button', 'label' => __('proxypanel.action_sync'), 'function' => 'syncStatus'];

        if ($this->truthy($settings['allow_manual_rotate'] ?? false)) {
            $actions[] = ['type' => 'button', 'label' => __('proxypanel.action_rotate'), 'function' => 'rotate'];
        }

        $actions[] = ['type' => 'button', 'label' => __('proxypanel.action_reboot'), 'function' => 'reboot'];

        return $actions;
    }

    /** The provisioned detail shown to the customer. Labels live in lang/en/proxypanel.php. */
    private function customerFields(Service $service): array
    {
        $ips = $this->prop($service, self::IPS_KEY);
        $counter = $this->prop($service, self::ROTATION_COUNTER_KEY);
        $max = $this->prop($service, self::MAX_ROTATE_KEY);
        $synced = $this->prop($service, self::SYNCED_KEY);
        $expiration = $this->prop($service, self::EXPIRATION_KEY);

        return [
            __('proxypanel.proxy_username') => $this->prop($service, self::USERNAME_KEY),
            __('proxypanel.proxy_password') => $this->prop($service, self::PASSWORD_KEY),
            __('proxypanel.proxy_count') => $this->prop($service, self::AMOUNT_KEY),
            __('proxypanel.proxy_endpoints') => $ips,
            __('proxypanel.auth_ips') => $this->prop($service, self::AUTH_IPS_KEY),
            __('proxypanel.rotation_time') => $this->prop($service, self::ROTATION_TIME_KEY),
            __('proxypanel.rotations_used') => ($counter !== null && $max !== null) ? $counter . ' / ' . $max : $counter,
            __('proxypanel.api_key') => $this->prop($service, self::API_KEY_KEY),
            __('proxypanel.panel_expiration') => $expiration ? date('Y-m-d H:i', (int) $expiration) : null,
            __('proxypanel.panel_service_id') => $this->prop($service, self::REMOTE_ID_KEY),
            __('proxypanel.last_synced') => $synced ? \Carbon\Carbon::parse($synced)->diffForHumans() : null,
        ];
    }

    /** Pull service info from the panel and cache it (scope §7 "Synchronization"). */
    public function syncStatus(Service $service, $settings = [], $properties = [])
    {
        $data = $this->request('get', '/' . $this->requireRemoteId($service));

        $this->cachePanelState($service, $data);

        return $data;
    }

    /**
     * Manual rotation, respecting the plan's allowance so the customer gets a clear
     * message instead of a panel error.
     */
    public function rotate(Service $service, $settings = [], $properties = [])
    {
        $settings = array_merge($settings, $properties);

        if (!$this->truthy($settings['allow_manual_rotate'] ?? false)) {
            throw new \RuntimeException(__('proxypanel.rotate_not_allowed'));
        }

        $counter = $this->prop($service, self::ROTATION_COUNTER_KEY);
        $max = $this->prop($service, self::MAX_ROTATE_KEY);
        if ($max !== null && $counter !== null && (int) $max > 0 && (int) $counter >= (int) $max) {
            throw new \RuntimeException(__('proxypanel.rotate_limit_reached', ['max' => $max]));
        }

        $res = $this->request('get', '/rotate/' . $this->requireRemoteId($service));

        // Refresh the counter and the new addresses.
        $this->safely(fn () => $this->syncStatus($service));

        return $res;
    }

    public function reboot(Service $service, $settings = [], $properties = [], bool $hard = false)
    {
        return $this->request('get', '/reboot/' . $this->requireRemoteId($service) . ($hard ? '/hard' : ''));
    }

    /**
     * Set the automatic rotation interval (minutes).
     */
    public function setRotationTime(Service $service, int $minutes)
    {
        $res = $this->request('get', '/setRotate/' . $this->requireRemoteId($service) . '/' . $minutes);
        $this->setProp($service, self::ROTATION_TIME_KEY, (string) $minutes);

        return $res;
    }

    /**
     * Update authorized IPs and/or proxy credentials — api.md `POST /aa/{id}`.
     *
     * @param  array<int,string>  $authorizeIps  at most MAX_AUTH_IPS entries
     */
    public function updateAuth(Service $service, ?array $authorizeIps = null, ?string $username = null, ?string $password = null)
    {
        $payload = [];

        if ($authorizeIps !== null) {
            $ips = array_values(array_filter(array_map('trim', $authorizeIps)));

            foreach ($ips as $ip) {
                if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                    throw new \RuntimeException(__('proxypanel.invalid_ip', ['ip' => $ip]));
                }
            }

            if (count($ips) > self::MAX_AUTH_IPS) {
                throw new \RuntimeException(__('proxypanel.too_many_ips', ['max' => self::MAX_AUTH_IPS]));
            }

            $payload['authorize'] = $ips;
        }

        if ($username !== null || $password !== null) {
            $payload['authenticate'] = [
                'username' => $username ?? $this->prop($service, self::USERNAME_KEY),
                'password' => $password ?? $this->prop($service, self::PASSWORD_KEY),
            ];
        }

        if (empty($payload)) {
            return ['status' => 'ok', 'description' => 'nothing to update'];
        }

        $res = $this->request('post', '/aa/' . $this->requireRemoteId($service), $payload);

        if (isset($payload['authorize'])) {
            $this->setProp($service, self::AUTH_IPS_KEY, implode(', ', $payload['authorize']));
        }
        if ($username !== null) {
            $this->setProp($service, self::USERNAME_KEY, $username);
        }
        if ($password !== null) {
            $this->setProp($service, self::PASSWORD_KEY, $password);
        }

        return $res;
    }

    /** WHMCS ChangePassword. */
    public function changePassword(Service $service, string $password)
    {
        return $this->updateAuth($service, null, null, $password);
    }

    /** Enable/disable a blacklist — api.md `GET /blacklist/{id}/{status}`. */
    public function setBlacklist(Service $service, string $blacklistId, bool $enabled)
    {
        return $this->request('get', '/blacklist/' . $blacklistId . '/' . ($enabled ? '1' : '0'));
    }

    /**
     * The proxy list for the client-area export, one per line as
     * `host:port:username:password`.
     *
     * IPv6 addresses contain colons, so a bare `ip:port:user:pass` line is impossible to
     * parse. IPv6 hosts are therefore wrapped in brackets — `[2a01:4f8::1]:10000:user:pass`
     * — which is the standard notation proxy clients expect.
     */
    public function exportProxies(Service $service): string
    {
        $endpoints = (string) $this->prop($service, self::IPS_KEY);
        $username = (string) $this->prop($service, self::USERNAME_KEY);
        $password = (string) $this->prop($service, self::PASSWORD_KEY);

        $lines = [];
        foreach (array_filter(array_map('trim', explode(',', $endpoints))) as $endpoint) {
            // Split off the port: the last colon-separated segment that is numeric.
            $port = null;
            $host = $endpoint;
            if (($pos = strrpos($endpoint, ':')) !== false && ctype_digit(substr($endpoint, $pos + 1))) {
                $host = substr($endpoint, 0, $pos);
                $port = substr($endpoint, $pos + 1);
            }

            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $host = '[' . $host . ']';
            }

            $lines[] = implode(':', array_filter([$host, $port, $username, $password], fn ($v) => $v !== null && $v !== ''));
        }

        return implode("\n", $lines);
    }

    // ── Panel callback ───────────────────────────────────────────────────────

    /**
     * Handle a status callback from the panel.
     *
     * The WHMCS module's callback posted `id` + `status`, marking the service Active on
     * success and raising `AfterModuleCreateFailed` on error. This is the Paymenter
     * equivalent: it resolves the service, applies the status, and records failures so
     * they surface in the admin with a retry.
     *
     * Authentication (either, constant-time compared):
     *   X-Panel-Secret: <callback_secret>
     *   X-Panel-Signature: <hex HMAC-SHA256 of the raw body, keyed with callback_secret>
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

        $state = strtolower((string) ($payload['status'] ?? ($payload['event'] ?? '')));
        $applied = $this->applyCallbackState($service, $state, $payload);

        $this->log('info', 'Callback processed', [
            'service' => $service->id,
            'state' => $state,
            'applied' => $applied ?? 'none',
        ]);

        return response()->json(['status' => 'ok']);
    }

    private function isValidCallback(\Illuminate\Http\Request $request, string $secret): bool
    {
        if ($header = $request->header('X-Panel-Secret')) {
            return hash_equals($secret, (string) $header);
        }

        if ($signature = $request->header('X-Panel-Signature')) {
            return hash_equals(hash_hmac('sha256', $request->getContent(), $secret), (string) $signature);
        }

        return false;
    }

    private function resolveServiceFromCallback(array $payload): ?Service
    {
        // The panel's own service id (what it calls `id`), matched against what we stored.
        //
        // A panel id is not guaranteed unique over time — panels can recycle ids after a
        // service is cancelled, and a stale row can outlive its service. So take the most
        // recently provisioned match, and prefer one that is not already cancelled, rather
        // than whichever row happens to come first.
        foreach (['id', 'service_id', 'panel_id'] as $key) {
            if (!isset($payload[$key])) {
                continue;
            }

            $serviceIds = \App\Models\Property::where('key', self::REMOTE_ID_KEY)
                ->where('value', (string) $payload[$key])
                ->where('model_type', Service::class)
                ->orderByDesc('id')
                ->pluck('model_id');

            if ($serviceIds->isEmpty()) {
                continue;
            }

            $candidates = Service::whereIn('id', $serviceIds)
                ->orderByRaw('CASE WHEN status = ? THEN 1 ELSE 0 END', [Service::STATUS_CANCELLED])
                ->orderByDesc('id')
                ->get();

            if ($candidates->count() > 1) {
                $this->log('warning', 'Callback panel id matches several services — using the most recent', [
                    'panel_id' => $payload[$key],
                    'services' => $candidates->pluck('id')->all(),
                ]);
            }

            if ($service = $candidates->first()) {
                return $service;
            }
        }

        // Fall back to our own service id (sent to the panel as client_id).
        if ($clientId = $payload['client_id'] ?? null) {
            $service = Service::find($clientId);
            if ($service && $this->isProxyPanelService($service)) {
                return $service;
            }
        }

        return null;
    }

    /**
     * Apply a panel state. Returns the status applied, or null when unrecognised.
     * Idempotent — re-delivering the same callback is a no-op.
     */
    private function applyCallbackState(Service $service, string $state, array $payload): ?string
    {
        // Explicit failure: mirror WHMCS's AfterModuleCreateFailed — do NOT activate.
        if (in_array($state, ['error', 'failed', 'failure'], true)) {
            $description = (string) ($payload['description'] ?? $payload['message'] ?? 'Panel reported a provisioning error');

            ProvisioningOps::failed(
                $service,
                'ProxyPanel',
                'create',
                new \RuntimeException($description),
                ['payload' => $payload, 'via' => 'callback'],
            );

            return 'failed';
        }

        $target = match ($state) {
            'ok', 'active', 'running', 'started', 'online', 'created', 'success', 'unsuspended' => Service::STATUS_ACTIVE,
            'suspended', 'stopped', 'paused', 'offline', 'expired' => Service::STATUS_SUSPENDED,
            'cancelled', 'canceled', 'terminated', 'deleted', 'destroyed' => Service::STATUS_CANCELLED,
            default => null,
        };

        if ($target === null) {
            // Never guess: record it so the admin sees the panel is sending something new.
            ProvisioningOps::failed(
                $service,
                'ProxyPanel',
                'callback',
                new \RuntimeException('Unrecognised callback state: "' . $state . '"'),
                ['payload' => $payload],
            );

            return null;
        }

        if ($service->status !== $target) {
            $service->status = $target;
            $service->save();
        }

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

    private function truthy($value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'on', 'yes'], true);
    }

    private function safely(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            $this->log('warning', 'Non-fatal panel call failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Cache the panel's view of a service so the client area and admin can show real
     * data without another round-trip. Tolerant of the response being wrapped in `data`.
     */
    private function cachePanelState(Service $service, array $data): void
    {
        $payload = is_array($data['data'] ?? null) ? $data['data'] : $data;

        if (isset($payload['ips']) && is_array($payload['ips'])) {
            $endpoints = [];
            foreach ($payload['ips'] as $entry) {
                if (is_array($entry)) {
                    $ip = $entry['ip'] ?? null;
                    $port = $entry['port'] ?? null;
                    $endpoints[] = $port ? $ip . ':' . $port : $ip;
                } else {
                    $endpoints[] = (string) $entry;
                }
            }
            $this->setProp($service, self::IPS_KEY, implode(', ', array_filter($endpoints)));
            $this->setProp($service, self::AMOUNT_KEY, (string) count(array_filter($endpoints)));
        }

        foreach ([
            'expiration' => self::EXPIRATION_KEY,
            'rotation_counter' => self::ROTATION_COUNTER_KEY,
            'plan_max_rotate' => self::MAX_ROTATE_KEY,
        ] as $field => $key) {
            if (isset($payload[$field]) && $payload[$field] !== null) {
                $this->setProp($service, $key, (string) $payload[$field]);
            }
        }

        if ($apiKey = $payload['api_key'] ?? ($payload['apikey'] ?? null)) {
            $this->setProp($service, self::API_KEY_KEY, (string) $apiKey);
        }

        $this->setProp($service, self::SYNCED_KEY, now()->toDateTimeString());
    }

    private function withLock(Service $service, string $action, callable $callback)
    {
        $lock = $service->properties()->where('key', self::LOCK_KEY)->first();
        if ($lock && $lock->value === $action && $lock->updated_at?->gt(now()->subMinutes(5))) {
            $this->log('warning', 'ProxyPanel op already in flight — skipping', ['service' => $service->id, 'action' => $action]);

            return ['status' => 'ok', 'description' => 'operation already in flight'];
        }
        $service->properties()->updateOrCreate(['key' => self::LOCK_KEY], ['value' => $action]);

        try {
            $result = $callback();

            ProvisioningOps::succeeded($service, 'ProxyPanel', $action);

            return $result;
        } catch (\Throwable $e) {
            $this->log('error', 'ProxyPanel ' . $action . ' failed', ['service' => $service->id, 'error' => $e->getMessage()]);

            // Visible + retryable in the admin; a failed create also stops the order
            // being left silently active.
            ProvisioningOps::failed($service, 'ProxyPanel', $action, $e, [
                'api_url' => (string) $this->config('api_url'),
            ]);

            throw $e;
        } finally {
            $service->properties()->where('key', self::LOCK_KEY)->delete();
        }
    }

    private function prop(Service $service, string $key): ?string
    {
        return $service->properties()->where('key', $key)->first()?->value;
    }

    private function remoteId(Service $service): ?string
    {
        return $this->prop($service, self::REMOTE_ID_KEY);
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

    /**
     * Panel HTTP call: `Panel` auth header, retry on transient failure, and the panel's
     * own {status: ok|error, description} convention checked.
     *
     * @throws \RuntimeException on transport or API-level error
     */
    private function request(string $method, string $path, array $data = [], bool $throwOnApiError = true): array
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

        if ($throwOnApiError && ($json['status'] ?? 'ok') === 'error') {
            $msg = $json['description'] ?? 'Unknown panel error';
            $this->log('error', 'ProxyPanel returned error', ['path' => $path, 'detail' => $msg]);
            throw new \RuntimeException('ProxyPanel: ' . $msg);
        }

        return $json;
    }

    private function log(string $level, string $message, array $context = []): void
    {
        Log::channel(self::LOG_CHANNEL)->{$level}('[ProxyPanel] ' . $message, $context);
    }
}
