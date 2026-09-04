<?php

namespace Paymenter\Extensions\Servers\ProxyPanel;

use App\Classes\Extension\Server;
use App\Events\Service\Updated;
use App\Helpers\ExtensionHelper;
use App\Models\Product;
use App\Models\Property;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Paymenter\Extensions\Others\ProvisioningOps\ProvisioningOps;
use Paymenter\Extensions\Servers\ProxyPanel\Support\CountryFlag;
use Paymenter\Extensions\Servers\ProxyPanel\Support\Endpoints;

/**
 * ProxyPanel — native Paymenter server (provisioning) module, converted from the client's
 * WHMCS "proxyPanel" module.
 *
 * /plans, /locations, /stop, /start, /credentials, /auth_ips, /rotate and /setRotate are
 * absent from api.md but live in production, so the working WHMCS module is authoritative:
 *
 *   GET  /{id}                          service info (expiration, rotation, ips[])
 *   POST /newIpv6                       create
 *   GET  /renew/{id}                    renew (clears counters)
 *   GET  /extend/{id}/{unixtimestamp}   set expiration
 *   GET  /expand|shrink/{id}/{amount}   add / remove proxies (upgrade / downgrade)
 *   GET  /stop/{id} · /start/{id}       suspend / unsuspend
 *   GET  /cancel/{id}                   immediate termination
 *   POST /credentials/{id}              username + password
 *   POST /auth_ips/{id}                 authorized IPs (max 3)
 *   GET  /rotate/{id}/1                 manual rotation
 *   GET  /setRotate/{id}/{minutes}      automatic rotation interval
 *   GET  /reboot/{id}[/hard]            reboot
 *   GET  /plans · /locations            catalogue for the Plan and Region dropdowns
 *   GET  /blacklist/{id}/{enabled|disabled}
 *
 * Auth: every call sends the `Panel: <token>` header. Responses are JSON with a
 * `status` field (`ok`/`error`) and an optional `description`.
 *
 * Renewal has no Paymenter hook: a Service\Updated listener calls /renew then /extend.
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

    /** Set only once the panel confirms the service is deployed. */
    private const CONFIRMED_KEY = 'proxy_confirmed_at';

    /** The panel accepts at most 3 authorized IPs. */
    private const MAX_AUTH_IPS = 3;

    /** Backstop on /locations/list paging: 246 locations at 100 a page needs 3. */
    private const MAX_LOCATION_PAGES = 20;

    /** @var array<int, array<string, mixed>>|null memoised /v0/locations/list, all pages */
    private ?array $locationCatalogue = null;

    /**
     * Endpoints listed on the management page before it defers to the export.
     *
     * The catalogue sells 1,500 to 31,500 proxies per service. Rendering them all produces a
     * page nobody scrolls and every browser struggles with; the Export button already hands
     * over the complete list in the format customers actually feed to their tooling.
     */
    private const MANAGE_PREVIEW = 100;

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
                // Password, not text (Leandro): the value is stored encrypted, but a text
                // input printed the decrypted token on screen every time the form opened —
                // one screenshot or shoulder-glance and the key has leaked. Masked now,
                // with the reveal eye for when it is genuinely needed.
                'type' => 'password',
                'description' => 'Sent as the "Panel" header on every request. Stored encrypted; shown masked.',
                'required' => true,
                'encrypted' => true,
            ],
            [
                'name' => 'callback_secret',
                'label' => 'Callback Secret',
                'type' => 'password',
                'description' => 'Shared secret the panel must present when calling back into Paymenter, '
                    . 'as "X-Panel-Secret" or as an HMAC-SHA256 of the raw body in "X-Panel-Signature". '
                    . 'Leave empty to disable the callback endpoint. Stored encrypted; shown masked.',
                'required' => false,
                'encrypted' => true,
            ],
            [
                'name' => 'region_flags',
                'label' => 'Show country flags on regions',
                'type' => 'checkbox',
                // Leandro tested it live on Windows (issue #43): the flag renders. The
                // vendored TwemojiCountryFlags webfont (AdminOps::boot(), this extension's
                // routes.php) covers exactly the gap Windows has none for, admin side and
                // client side both — the caveat this description used to carry was true of
                // an unpatched browser, not of this install, and read as a bug report.
                'description' => 'Prefix each Region at checkout with its country flag, e.g. '
                    . '"🇺🇸  United States - Kansas City".',
                'default' => true,
                'required' => false,
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

    /**
     * Creates `proxypanel_endpoints` (see its migration for why it exists).
     *
     * Paymenter runs this when the extension is enabled. ProxyPanel was already enabled when
     * the table was introduced, so on an existing install run it once by hand:
     *
     *   php artisan tinker --execute="\App\Helpers\ExtensionHelper::runMigrations('extensions/Servers/ProxyPanel/database/migrations');"
     *
     * Everything degrades to the old property until it exists, so the order does not matter.
     */
    public function installed()
    {
        ExtensionHelper::runMigrations('extensions/Servers/ProxyPanel/database/migrations');
    }

    public function uninstalled()
    {
        ExtensionHelper::rollbackMigrations('extensions/Servers/ProxyPanel/database/migrations');
    }

    public function boot()
    {
        require __DIR__ . '/routes.php';
        View::addNamespace('servers.proxypanel', __DIR__ . '/resources/views');

        // Renewal is billing-driven — Paymenter has no renew hook. When a ProxyPanel
        // service's expires_at moves (e.g. after an invoice is paid), push it to the panel.
        // Guard: never let a ProxyPanel service sit at "active" unless the panel has
        // confirmed it. Core activates independently of the provisioning job, so this
        // catches it whichever way round the two happen.
        Event::listen(Updated::class, function ($event) {
            $service = $event->service;

            if (!$this->isProxyPanelService($service) || !$service->isDirty('status')) {
                return;
            }

            if ($service->status !== Service::STATUS_ACTIVE || $this->isConfirmed($service)) {
                return;
            }

            // Only gate the initial provisioning. Once a service has been confirmed at
            // least once, later suspend/unsuspend cycles activate normally.
            if (!$this->remoteId($service)) {
                return;
            }

            $this->forceStatus($service, Service::STATUS_PENDING);

            $this->log('info', 'Held service at pending — panel has not confirmed deployment yet', [
                'service' => $service->id,
            ]);
        });

        Event::listen(Updated::class, function ($event) {
            $service = $event->service;
            if (!$this->isProxyPanelService($service) || !$service->isDirty('expires_at') || !$service->expires_at) {
                return;
            }
            try {
                if ($id = $this->remoteId($service)) {
                    // /renew clears the rotation counters for the new period; /extend sets
                    // the actual expiry. api.md documents both.
                    $this->request('get', '/renew/' . $id);
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

    /**
     * Mirrors the WHMCS module's proxypanel_ConfigOptions(), including the Plan dropdown
     * populated live from the panel's /plans endpoint.
     */
    public function getProductConfig($values = []): array
    {
        return [
            [
                'name' => 'amount',
                'label' => 'Amount proxies',
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
                'options' => $this->safeOptions(fn () => $this->fetchOptions('/plans')),
                'required' => true,
            ],
            [
                'name' => 'protocol',
                'label' => 'Protocol',
                'type' => 'select',
                'description' => 'Recorded on the service and shown to the customer. The panel API '
                    . 'exposes no protocol field, so it is not sent upstream — see '
                    . 'docs/modules/proxypanel.md § Protocol.',
                'options' => ['http' => 'HTTP', 'https' => 'HTTPS', 'socks5' => 'SOCKS5'],
                'required' => false,
            ],
            [
                'name' => 'allow_rotation',
                'label' => 'Allow manual Rotation',
                'type' => 'select',
                'options' => ['yes' => 'Yes', 'no' => 'No'],
                'default' => 'yes',
                'required' => false,
            ],
            [
                'name' => 'change_rotation',
                'label' => 'Allow change rotation time',
                'type' => 'select',
                'options' => ['yes' => 'Yes', 'no' => 'No'],
                'default' => 'yes',
                'required' => false,
            ],
            [
                'name' => 'auth_ips',
                'label' => 'How many auth_ips can be allowed',
                'type' => 'text',
                'description' => 'Zero or empty means disabled. The panel accepts at most ' . self::MAX_AUTH_IPS . '.',
                'validation' => 'numeric',
                'required' => false,
            ],
            [
                'name' => 'amount_rotations',
                'label' => 'How many rotations per period are allowed',
                'type' => 'text',
                'description' => 'Zero or empty means unlimited.',
                'validation' => 'numeric',
                'required' => false,
            ],
            [
                'name' => 'bwlimit',
                'label' => 'Bandwidth limit',
                'type' => 'text',
                'description' => 'Zero or empty means unlimited.',
                'validation' => 'numeric',
                'required' => false,
            ],
        ];
    }

    /**
     * The Region select, built from `GET /v0/locations/list` — the only endpoint that reports
     * capacity (`total` tunnels, `free` of them unused, enabled/disabled `status`).
     * `/v0/services/locations` returns only the in-stock names, so it cannot mark the rest.
     *
     * Offered once a location has capacity at all (`total > 0`); sellable only while enabled
     * with `free > 0`. The rest are listed, marked and disabled, as the reference does.
     *
     * Option values are the "Country - City" label, not the tag: that is what the panel
     * expects back as `location_name`.
     */
    public function getCheckoutConfig(Product $product): array
    {
        $serverId = $product->server_id;

        [$regions, $unavailable] = $this->rememberedRegions($serverId);
        $stale = false;

        // null means the call failed. Nothing is invented to fill the gap: the last answer
        // the panel actually gave is reused and the customer is told it may be out of date.
        if (($live = $this->safeLocationCatalogue()) !== null) {
            $regions = [];
            $unavailable = [];

            foreach ($live as $row) {
                $label = trim(($row['country_name'] ?? '') . ' - ' . ($row['city'] ?? ''));

                if ($label === '-' || (int) ($row['total'] ?? 0) <= 0) {
                    continue;                 // no tunnels here: never offered, as upstream
                }

                $regions[$label] = $label;

                if (($row['status'] ?? 'enabled') !== 'enabled' || (int) ($row['free'] ?? 0) <= 0) {
                    $unavailable[] = $label;
                }
            }

            ksort($regions);
            $this->rememberRegions($serverId, $regions, $unavailable);
        } else {
            $stale = true;
        }

        // No regions and no usable cache: return an empty select carrying the notice rather
        // than nothing at all. Returning [] drops the Configurable Options block entirely and
        // bounces the order form to the cart with no explanation.
        if (empty($regions)) {
            return [
                [
                    'name' => 'Region',
                    'label' => 'Region',
                    'type' => 'select',
                    'required' => true,
                    'options' => ['' => __($stale ? 'proxypanel.regions_unavailable' : 'proxypanel.regions_none')],
                    'disabled_options' => [],
                ],
            ];
        }

        // Shown but marked and disabled, as the WHMCS order form did it: the customer sees
        // the region exists and picks another, rather than wondering where it went.
        foreach ($unavailable as $tag) {
            if (isset($regions[$tag])) {
                $regions[$tag] .= ' ' . __('proxypanel.out_of_stock');
            }
        }

        // Prefix each region with its country flag, e.g. "🇺🇸  United States - Kansas City".
        if ($this->config('region_flags') === null || $this->truthy($this->config('region_flags'))) {
            $regions = array_map(fn ($label) => CountryFlag::decorate((string) $label), $regions);
        }

        // Placeholder first. Its key is '' so core seeds the field with it (array_key_first)
        // and `required` then refuses the submit until a real region is chosen. When the
        // panel could not be reached it doubles as the notice that this list may be stale.
        $regions = ['' => __($stale ? 'proxypanel.regions_stale' : 'proxypanel.region_placeholder')] + $regions;

        return [
            [
                'name' => 'Region',
                'label' => 'Region',
                'type' => 'select',
                'required' => true,
                'options' => $regions,
                'disabled_options' => $unavailable,
            ],
        ];
    }

    /**
     * Every page of `GET /v0/locations/list` (docs/client-brief/locations.md).
     *
     * Paged on `total`/`items_per_page`, never `total_pages`: the panel reports 2 pages for
     * 246 locations at 100 each, and page 3 does return the missing 46. Memoised per
     * instance — ExtensionHelper builds a fresh one per resolution, so it cannot go stale.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchLocationCatalogue(): array
    {
        if ($this->locationCatalogue !== null) {
            return $this->locationCatalogue;
        }

        $rows = [];
        $seen = [];
        $page = 1;
        $expected = null;

        do {
            $body = $this->request('get', '/locations/list?page=' . $page, [], true, $this->locationsBase());
            $batch = (array) ($body['locations'] ?? []);

            if ($expected === null) {
                $perPage = max(1, (int) ($body['items_per_page'] ?? 100));
                $expected = (int) ceil((int) ($body['total'] ?? count($batch)) / $perPage);
            }

            foreach ($batch as $row) {
                $tag = $row['tag'] ?? null;
                if ($tag !== null && isset($seen[$tag])) {
                    continue;               // a repeated page must not double the list
                }
                $seen[$tag] = true;
                $rows[] = (array) $row;
            }

            $page++;
        } while ($batch !== [] && $page <= min($expected, self::MAX_LOCATION_PAGES));

        return $this->locationCatalogue = $rows;
    }

    /** `api_url` points at `…/v0/services`; the location API is its sibling `…/v0/locations`. */
    private function locationsBase(): string
    {
        $url = rtrim((string) $this->config('api_url'), '/');

        return preg_replace('#/services$#', '', $url) ?: $url;
    }

    /** @return array<int, array<string, mixed>>|null null when the panel could not be reached */
    private function safeLocationCatalogue(): ?array
    {
        try {
            return $this->fetchLocationCatalogue();
        } catch (\Throwable $e) {
            $this->log('warning', 'ProxyPanel location catalogue fetch failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /** Enabled, with at least one free tunnel. */
    private function locationIsSellable(string $label): bool
    {
        foreach ($this->fetchLocationCatalogue() as $row) {
            if (trim(($row['country_name'] ?? '') . ' - ' . ($row['city'] ?? '')) !== $label) {
                continue;
            }

            return ($row['status'] ?? 'enabled') === 'enabled' && (int) ($row['free'] ?? 0) > 0;
        }

        return false;
    }

    /**
     * `GET /plans` and `GET /locations` as `[tag => label]`.
     *
     * The live panel answers with a bare JSON array of label strings; `data`-wrapped and
     * object-row forms are accepted too, since neither endpoint is in api.md.
     */
    private function fetchOptions(string $path): array
    {
        $data = $this->request('get', $path);
        $rows = $data['data'] ?? $data;
        $out = [];

        foreach ((array) $rows as $key => $value) {
            if ($key === 'status' || $key === 'description') {
                continue;
            }
            if (is_array($value)) {
                $tag = $value['tag'] ?? $value['name'] ?? $value['id'] ?? $key;
                $out[$tag] = $value['name'] ?? $tag;
            } else {
                $out[$value] = $value;
            }
        }

        return $out;
    }

    /**
     * The last region list the panel gave us, so the order form survives an outage. Kept in
     * `settings` rather than the cache: a memory-only copy would be empty right after a
     * deploy, exactly when it is needed.
     */
    private function rememberRegions(?int $serverId, array $regions, array $unavailable = []): void
    {
        if (!$serverId) {
            return;
        }

        try {
            DB::table('settings')->updateOrInsert(
                [
                    'settingable_type' => \App\Models\Server::class,
                    'settingable_id' => $serverId,
                    'key' => 'cached_regions',
                ],
                [
                    // Availability alongside the labels: labels alone meant a cached list
                    // came back with every region looking available.
                    'value' => json_encode(['regions' => $regions, 'unavailable' => array_values($unavailable)]),
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        } catch (\Throwable $e) {
            // Caching is an optimisation; never let it break the order form.
            $this->log('warning', 'ProxyPanel could not cache regions', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @return array{0: array<string,string>, 1: array<int,string>} labels, unavailable tags
     */
    private function rememberedRegions(?int $serverId): array
    {
        if (!$serverId) {
            return [[], []];
        }

        try {
            $raw = DB::table('settings')
                ->where('settingable_type', \App\Models\Server::class)
                ->where('settingable_id', $serverId)
                ->where('key', 'cached_regions')
                ->value('value');

            $data = $raw ? (array) json_decode($raw, true) : [];

            // Entries written before availability was cached are a flat tag => label map.
            // Unknown availability is treated as unavailable, not assumed good.
            if (!array_key_exists('regions', $data)) {
                return [$data, array_keys($data)];
            }

            return [(array) ($data['regions'] ?? []), array_values((array) ($data['unavailable'] ?? []))];
        } catch (\Throwable $e) {
            return [[], []];
        }
    }

    /**
     * Like safeOptions(), but tells "could not ask" apart from "asked, answer is none" —
     * a distinction checkout needs and the admin dropdowns do not.
     *
     * @return array<string,string>|null null when the call failed
     */
    private function tryOptions(string $path): ?array
    {
        try {
            return $this->fetchOptions($path);
        } catch (\Throwable $e) {
            $this->log('warning', 'ProxyPanel option fetch failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /** A catalogue fetch must never break the admin form when the panel is unreachable. */
    private function safeOptions(callable $fetch): array
    {
        try {
            return $fetch();
        } catch (\Throwable $e) {
            $this->log('warning', 'ProxyPanel option fetch failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    // ── Lifecycle ────────────────────────────────────────────────────────────

    public function createServer(Service $service, $settings, $properties)
    {
        $settings = array_merge($settings, $properties);

        // Paying the invoice marks the service Active before provisioning is attempted, so
        // the gate has to hold on *every* exit — not just the happy path. Otherwise a panel
        // refusal (no location, bad plan, outage) leaves the customer an Active service that
        // was never delivered. Rethrow after, so ProvisioningOps records and can retry it.
        try {
            return $this->createServerInternal($service, $settings);
        } catch (\Throwable $e) {
            try {
                $this->awaitPanelConfirmation($service);
            } catch (\Throwable $ignored) {
                // Never let the guard mask the original provisioning error.
            }

            throw $e;
        }
    }

    private function createServerInternal(Service $service, array $settings)
    {
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
            $password = substr(sha1(random_bytes(10)), 0, 8);
            $amount = max(1, (int) ($settings['amount'] ?? 1));
            $bwlimit = (int) ($settings['bwlimit'] ?? 0);

            // `client_id` is the Paymenter service id — the WHMCS module's callback looks
            // the service up by that same id.
            $payload = [
                'client_id' => (int) $service->id,
                'plan_tag' => $settings['plan'] ?? null,
                'location_name' => $settings['Region'] ?? ($settings['region'] ?? null),
                'amount' => $amount,
                'authenticate' => ['username' => $username, 'password' => $password],
                'bwlimit' => $bwlimit > 0 ? $bwlimit : null,
            ];

            // Re-checked here, not just at checkout: a cart can sit for days between the
            // region being picked and the invoice being paid.
            $location = $payload['location_name'];
            if (!$location || !$this->locationIsSellable($location)) {
                throw new \RuntimeException('ProxyPanel: selected Region is out of stock.');
            }

            if (empty($payload['plan_tag'])) {
                throw new \RuntimeException('ProxyPanel: no Plan configured on this product.');
            }

            $res = $this->request('post', '/newIpv6', $payload);

            $newId = $res['id'] ?? ($res['service_id'] ?? ($res['data']['id'] ?? null));
            if (!$newId) {
                throw new \RuntimeException('ProxyPanel create returned no service id.');
            }

            $this->setProp($service, self::REMOTE_ID_KEY, $newId);
            $this->setProp($service, self::USERNAME_KEY, $username);
            $this->setProp($service, self::PASSWORD_KEY, $password);
            $this->setProp($service, self::AMOUNT_KEY, (string) $amount);

            // The api-key is generated by us, not returned by the panel — same as the
            // WHMCS module (`'api-key' => sha1(random_bytes(32))`).
            $apiKey = $res['api_key'] ?? ($res['apikey'] ?? sha1(random_bytes(32)));
            $this->setProp($service, self::API_KEY_KEY, (string) $apiKey);

            $this->cachePanelState($service, $res);

            // Provisioning is asynchronous: an ok from /newIpv6 means accepted, not
            // deployed. Stay Pending until the panel's callback says otherwise, so a
            // customer is never shown a service the panel has not delivered.
            $this->awaitPanelConfirmation($service);

            $this->log('info', 'Service provisioning requested — awaiting panel confirmation', [
                'service' => $service->id,
                'remote' => $newId,
            ]);

            return $res;
        });
    }

    /** WHMCS SuspendAccount → GET /stop/{id}. */
    public function suspendServer(Service $service, $settings, $properties)
    {
        return $this->withLock($service, 'suspend', fn () => $this->request('get', '/stop/' . $this->requireRemoteId($service)));
    }

    /** WHMCS UnsuspendAccount → GET /start/{id}. */
    public function unsuspendServer(Service $service, $settings, $properties)
    {
        return $this->withLock($service, 'unsuspend', fn () => $this->request('get', '/start/' . $this->requireRemoteId($service)));
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

        if ($this->truthy($settings['allow_rotation'] ?? false)) {
            $actions[] = ['type' => 'button', 'label' => __('proxypanel.action_rotate'), 'function' => 'rotate'];
        }

        $actions[] = ['type' => 'button', 'label' => __('proxypanel.action_reboot'), 'function' => 'reboot'];

        // The management panel (proxy list, export, auth IPs, rotation, password).
        $actions[] = ['type' => 'view', 'name' => 'manage', 'label' => __('proxypanel.manage_title')];

        return $actions;
    }

    /** The provisioned detail shown to the customer. Labels live in lang/en/proxypanel.php. */
    private function customerFields(Service $service): array
    {
        // A count, not the list. This renders as a single line on the service page, and the
        // list can be 31,500 entries — the management view's table and the export are where
        // the endpoints themselves belong.
        $endpointCount = Endpoints::count($service);
        $ips = $endpointCount > 0 ? (string) $endpointCount : null;
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
            __('proxypanel.last_synced') => $synced ? Carbon::parse($synced)->diffForHumans() : null,
        ];
    }

    /**
     * The proxy management panel on the customer's service page. Core echoes the HTML raw
     * and its `goto()` helper passes no arguments, so the forms post to this extension's own
     * routes, which authorize against the Service policy and call the `client*` methods.
     */
    public function getView(Service $service, $settings = [], $properties = [], $view = null)
    {
        $settings = array_merge($settings, $properties);

        return view('servers.proxypanel::manage', [
            'service' => $service,
            'endpoints' => $this->endpointList($service),
            // The table shows at most MANAGE_PREVIEW rows; the total is what tells the
            // customer whether they are looking at all of them.
            'endpointTotal' => Endpoints::count($service),
            'endpointPreview' => self::MANAGE_PREVIEW,
            'authIps' => array_filter(array_map('trim', explode(',', (string) $this->prop($service, self::AUTH_IPS_KEY)))),
            'maxAuthIps' => min(self::MAX_AUTH_IPS, (int) ($settings['auth_ips'] ?? self::MAX_AUTH_IPS)),
            'rotationTime' => $this->prop($service, self::ROTATION_TIME_KEY),
            'rotationCounter' => $this->prop($service, self::ROTATION_COUNTER_KEY),
            'maxRotate' => $this->prop($service, self::MAX_ROTATE_KEY),
            'canChangeRotation' => $this->truthy($settings['change_rotation'] ?? false),
            'canRotate' => $this->truthy($settings['allow_rotation'] ?? false),
            'username' => $this->prop($service, self::USERNAME_KEY),
            'apiKey' => $this->prop($service, self::API_KEY_KEY),
        ])->render();
    }

    /**
     * `ip:port` endpoints for the management table.
     *
     * Capped: a Ruby service has 31,500 of them, and rendering that many rows produces a
     * page no browser handles well and nobody reads. The table shows the first
     * `MANAGE_PREVIEW`, says how many there are in total, and the Export button — which
     * streams — remains the way to get all of them.
     */
    private function endpointList(Service $service): array
    {
        return Endpoints::all($service, self::MANAGE_PREVIEW);
    }

    // ── Client-initiated actions (called via this extension's routes) ────────
    // Signature follows ExtensionHelper::callService(): ($service, $settings, $properties,
    // ...$args). Permission flags are enforced here, never trusted from the form.

    public function clientUpdateAuthIps(Service $service, $settings, $properties, array $ips)
    {
        $settings = array_merge($settings, $properties);
        $allowed = min(self::MAX_AUTH_IPS, (int) ($settings['auth_ips'] ?? self::MAX_AUTH_IPS));

        $ips = array_values(array_filter(array_map('trim', $ips)));
        if (count($ips) > $allowed) {
            throw new \RuntimeException(__('proxypanel.too_many_ips', ['max' => $allowed]));
        }

        return $this->updateAuth($service, $ips);
    }

    public function clientUpdatePassword(Service $service, $settings, $properties, string $password)
    {
        if (strlen($password) < 8) {
            throw new \RuntimeException(__('proxypanel.password_too_short'));
        }

        return $this->changePassword($service, $password);
    }

    public function clientUpdateRotation(Service $service, $settings, $properties, int $minutes)
    {
        $settings = array_merge($settings, $properties);

        if (!$this->truthy($settings['change_rotation'] ?? false)) {
            throw new \RuntimeException(__('proxypanel.rotation_change_not_allowed'));
        }

        if ($minutes < 0) {
            throw new \RuntimeException(__('proxypanel.invalid_rotation_time'));
        }

        return $this->setRotationTime($service, $minutes);
    }

    public function clientExport(Service $service, $settings = [], $properties = []): string
    {
        return $this->exportProxies($service);
    }

    /** Pull service info from the panel and cache it (scope §7 "Synchronization"). */
    public function syncStatus(Service $service, $settings = [], $properties = [])
    {
        $data = $this->request('get', '/' . $this->requireRemoteId($service));

        $this->cachePanelState($service, $data);

        // A sync is also a confirmation: if the panel now reports the service deployed,
        // activate it. This is the manual fallback when a callback never arrives.
        $payload = is_array($data['data'] ?? null) ? $data['data'] : $data;
        if ($this->panelReportsDeployed($payload) && !$this->isConfirmed($service)) {
            $this->confirmActivation($service, 'sync');
        }

        return $data;
    }

    /**
     * Does this panel payload describe a deployed, usable service?
     *
     * The panel's list view shows "deployed: none / status: pending" until it finishes,
     * so proxies actually being assigned is the reliable signal.
     */
    private function panelReportsDeployed(array $payload): bool
    {
        $state = strtolower((string) ($payload['status'] ?? $payload['state'] ?? ''));

        if (in_array($state, ['pending', 'error', 'failed'], true)) {
            return false;
        }

        $deployed = $payload['deployed'] ?? null;
        if ($deployed !== null && in_array(strtolower((string) $deployed), ['none', '0', 'false', ''], true)) {
            return false;
        }

        return !empty($payload['ips']);
    }

    /**
     * Manual rotation, respecting the plan's allowance so the customer gets a clear
     * message instead of a panel error.
     */
    public function rotate(Service $service, $settings = [], $properties = [])
    {
        $settings = array_merge($settings, $properties);

        if (!$this->truthy($settings['allow_rotation'] ?? false)) {
            throw new \RuntimeException(__('proxypanel.rotate_not_allowed'));
        }

        $counter = $this->prop($service, self::ROTATION_COUNTER_KEY);
        $max = $this->prop($service, self::MAX_ROTATE_KEY);
        if ($max !== null && $counter !== null && (int) $max > 0 && (int) $counter >= (int) $max) {
            throw new \RuntimeException(__('proxypanel.rotate_limit_reached', ['max' => $max]));
        }

        $res = $this->request('get', '/rotate/' . $this->requireRemoteId($service) . '/1');

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

        $id = $this->requireRemoteId($service);
        $result = null;

        if (isset($payload['authorize'])) {
            $result = $this->request('post', '/auth_ips/' . $id, ['ips' => $payload['authorize']]);
            $this->setProp($service, self::AUTH_IPS_KEY, implode(', ', $payload['authorize']));
        }

        if (isset($payload['authenticate'])) {
            $result = $this->request('post', '/credentials/' . $id, $payload['authenticate']);
            if ($username !== null) {
                $this->setProp($service, self::USERNAME_KEY, $username);
            }
            if ($password !== null) {
                $this->setProp($service, self::PASSWORD_KEY, $password);
            }
        }

        return $result ?? ['status' => 'ok'];
    }

    /** WHMCS ChangePassword. */
    public function changePassword(Service $service, string $password)
    {
        return $this->updateAuth($service, null, null, $password);
    }

    /** Enable/disable a blacklist — api.md `GET /blacklist/{id}/{status}`. */
    public function setBlacklist(Service $service, string $blacklistId, bool $enabled)
    {
        return $this->request('get', '/blacklist/' . $blacklistId . '/' . ($enabled ? 'enabled' : 'disabled'));
    }

    /**
     * The client-area export, one `host:port:username:password` per line. IPv6 hosts are
     * bracketed — `[2a01:4f8::1]:10000:user:pass` — or the colons make the line unparseable.
     */
    public function exportProxies(Service $service): string
    {
        $username = (string) $this->prop($service, self::USERNAME_KEY);
        $password = (string) $this->prop($service, self::PASSWORD_KEY);

        $lines = [];

        // Chunked: the largest tier exports about 1.7 MB, and holding every row and every
        // rendered line at once is avoidable.
        Endpoints::each($service, function (array $endpoints) use (&$lines, $username, $password) {
            foreach ($endpoints as $endpoint) {
                [$host, $port] = Endpoints::split($endpoint);

                if ($host === null) {
                    continue;
                }

                if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    $host = '[' . $host . ']';
                }

                $lines[] = implode(':', array_filter(
                    [$host, $port, $username, $password],
                    fn ($v) => $v !== null && $v !== '' && $v !== 0,
                ));
            }
        });

        return implode("\n", $lines);
    }

    // ── Activation gating ────────────────────────────────────────────────────

    /**
     * Write a status straight to the row.
     *
     * The guard runs inside the model's own `updated` event, where the attribute is written
     * but `original` still holds the pre-save value — so assigning the old value back leaves
     * the model *not dirty* and `save()` would write nothing. Go through the query builder
     * and re-sync the attribute; this also avoids re-entering the observer.
     */
    private function forceStatus(Service $service, string $status): void
    {
        Service::whereKey($service->getKey())->update(['status' => $status]);

        $service->setAttribute('status', $status);
        $service->syncOriginalAttribute('status');
    }

    /**
     * Hold the service at `pending` until the panel confirms deployment. Core activates in
     * RenewServiceService independently of the provisioning job — before or after it — so
     * returning from createServer() is not enough; the Service\Updated listener in boot()
     * reverts any activation the panel has not confirmed, covering both orderings.
     */
    private function awaitPanelConfirmation(Service $service): void
    {
        $this->clearProp($service, self::CONFIRMED_KEY);

        if ($service->status === Service::STATUS_ACTIVE) {
            $this->forceStatus($service, Service::STATUS_PENDING);
        }
    }

    /** Record that the panel has confirmed the service, and activate it. */
    private function confirmActivation(Service $service, string $via): void
    {
        $this->setProp($service, self::CONFIRMED_KEY, now()->toDateTimeString());

        if ($service->status !== Service::STATUS_ACTIVE) {
            $this->forceStatus($service, Service::STATUS_ACTIVE);
        }

        ProvisioningOps::succeeded($service, 'ProxyPanel', 'create', ['via' => $via]);
        $this->log('info', 'Panel confirmed the service — now active', ['service' => $service->id, 'via' => $via]);
    }

    /** True once the panel has told us the service is really deployed. */
    private function isConfirmed(Service $service): bool
    {
        return $this->prop($service, self::CONFIRMED_KEY) !== null;
    }

    // ── Panel callback ───────────────────────────────────────────────────────

    /**
     * Handle a status callback from the panel: resolve the service, apply the status, and
     * record failures so they surface in the admin with a retry.
     *
     * Authentication (either, constant-time compared):
     *   X-Panel-Secret: <callback_secret>
     *   X-Panel-Signature: <hex HMAC-SHA256 of the raw body, keyed with callback_secret>
     */
    public function callback(Request $request)
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

    private function isValidCallback(Request $request, string $secret): bool
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
        // Panel ids are not unique over time — they can be recycled after a cancellation —
        // so take the most recently provisioned match, preferring one not already cancelled.
        foreach (['id', 'service_id', 'panel_id'] as $key) {
            if (!isset($payload[$key])) {
                continue;
            }

            $serviceIds = Property::where('key', self::REMOTE_ID_KEY)
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

        if ($target === Service::STATUS_ACTIVE) {
            // This is the panel telling us the service is really deployed — the only
            // thing that may activate it.
            $this->confirmActivation($service, 'callback');

            return $target;
        }

        if ($service->status !== $target) {
            $service->status = $target;
            $service->save();
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
        return in_array($value, [true, 1, '1', 'true', 'on', 'yes'], true)
            || (is_string($value) && strtolower(trim($value)) === 'yes');
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
    /**
     * The customer's `host:port` endpoints. The live panel returns three shapes:
     *
     *  1. `"ips": [{"ip": null, "port": 10000, "out": "2a10:500:…"}]` — just after create,
     *     before a node is assigned; `out` carries the outbound address.
     *  2. `"ips": null` — still undeployed.
     *  3. `{"ip": "23.159.233.5", "first": 10000, "last": 10000, "amount": 1}` — deployed:
     *     one host with a port *range*, not a list.
     *
     * @return array<int,string>
     */
    private function endpointsFrom(array $payload): array
    {
        // Shape 3: a single host plus a first..last port range.
        $host = $payload['ip'] ?? null;
        $first = $payload['first'] ?? null;
        $last = $payload['last'] ?? null;

        if ($host && is_numeric($first) && is_numeric($last) && (int) $last >= (int) $first) {
            // Guard against a nonsense range returning an enormous list.
            $count = min((int) $last - (int) $first + 1, 1000);
            $out = [];
            for ($i = 0; $i < $count; $i++) {
                $out[] = $this->formatEndpoint((string) $host, (int) $first + $i);
            }

            return $out;
        }

        // Shapes 1 and 2: an ips[] list, possibly null.
        if (!isset($payload['ips']) || !is_array($payload['ips'])) {
            return [];
        }

        $out = [];
        foreach ($payload['ips'] as $entry) {
            if (!is_array($entry)) {
                $out[] = (string) $entry;

                continue;
            }

            // `ip` is null until a node is assigned; `out` is the outbound address.
            $addr = $entry['ip'] ?? ($entry['out'] ?? null);
            if (!$addr) {
                continue;                    // not deployed yet — nothing to show
            }

            $out[] = isset($entry['port'])
                ? $this->formatEndpoint((string) $addr, (int) $entry['port'])
                : (string) $addr;
        }

        return array_values(array_filter($out));
    }

    /** IPv6 hosts are bracketed so `host:port` stays unambiguous. */
    private function formatEndpoint(string $host, int $port): string
    {
        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
            ? '[' . $host . ']:' . $port
            : $host . ':' . $port;
    }

    private function cachePanelState(Service $service, array $data): void
    {
        $payload = is_array($data['data'] ?? null) ? $data['data'] : $data;

        $endpoints = $this->endpointsFrom($payload);

        if ($endpoints !== []) {
            // Rows, not a comma-joined property: `properties.value` is TEXT and every
            // product in the catalogue sells more endpoints than it holds, so this used to
            // throw "Data too long for column 'value'" and fail the whole provisioning run
            // after the panel had already allocated the proxies. See Support/Endpoints.
            Endpoints::replace($service, $endpoints);
            $this->setProp($service, self::AMOUNT_KEY, (string) count($endpoints));
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
    private function request(string $method, string $path, array $data = [], bool $throwOnApiError = true, ?string $base = null): array
    {
        $url = rtrim($base ?? (string) $this->config('api_url'), '/') . $path;

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

        $body = trim($response->body());
        $json = $response->json();

        // The panel answers an auth failure with HTTP 200 and a plain-text body ("Unable to
        // authorize your request"), not 401 + JSON — verified live. Without this check a bad
        // token looks like success and /stop or /cancel appear to have worked.
        if (!is_array($json)) {
            $detail = $body === '' ? 'empty response' : $body;
            $this->log('error', 'ProxyPanel returned a non-JSON response', [
                'path' => $path,
                'status' => $response->status(),
                'body' => \Str::limit($detail, 200),
            ]);

            throw new \RuntimeException(
                stripos($detail, 'authoriz') !== false
                    ? 'ProxyPanel rejected the API token — check the Panel Token setting.'
                    : 'ProxyPanel returned an unexpected response: ' . \Str::limit($detail, 200)
            );
        }

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
