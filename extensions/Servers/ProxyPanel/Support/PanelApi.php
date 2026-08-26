<?php

namespace Paymenter\Extensions\Servers\ProxyPanel\Support;

use App\Models\Server;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The panel API as seen from the admin panel rather than from a service.
 *
 * `ProxyPanel` is a `Server` extension whose `config()` resolves settings by walking the call
 * stack, so it needs a `Service`. The Locations console manages the panel's own infrastructure
 * and has none, so it needs a client built from the `Server` row alone. That is all this is.
 *
 * Live-panel state (probed 2026-08-26): `locations/*` and `tunnels/list` all work;
 * `tunnels/{id}/class/{class}` and `tunnels/info/{id}/class/{class}` answer 404, and
 * `tunnels/new|update|delete|status` are untested because they mutate real infrastructure.
 * The two 404s are worked around per-method. See docs/PANEL-QUESTIONS.md.
 *
 * @link docs/client-brief/locations.md
 * @link docs/client-brief/tunnels.md
 */
class PanelApi
{
    /** Backstop on paging: 246 locations at 100 a page needs 3, so 20 is far past any real answer. */
    private const MAX_PAGES = 20;

    private const TIMEOUT = 20;

    /** @var array<string, string> */
    private array $config;

    /** @var array<int, array<string, mixed>>|null */
    private ?array $locations = null;

    /** @var array<int, array<string, mixed>>|null memoised /v0/tunnels/list, all pages */
    private ?array $tunnelCache = null;

    public function __construct(Server $server)
    {
        $this->config = $server->settings->pluck('value', 'key')->toArray();
    }

    /**
     * The configured ProxyPanel server, or null when none is set up yet.
     *
     * Returns null rather than throwing so the admin page can render an explanatory empty
     * state instead of a 500 on a fresh install.
     */
    public static function resolve(): ?self
    {
        $server = Server::where('extension', 'ProxyPanel')->first();

        return $server ? new self($server) : null;
    }

    public function isConfigured(): bool
    {
        return filled($this->config['api_url'] ?? null) && filled($this->config['api_token'] ?? null);
    }

    // ── Locations ────────────────────────────────────────────────────────────

    /**
     * Every location the panel knows about, across all pages.
     *
     * Paged on `total`/`items_per_page`, never on `total_pages`: the live panel reports
     * `total_pages: 2` for 246 rows at 100 a page, and page 3 does return the missing 46.
     * Verified again on 2026-08-25.
     *
     * @return array<int, array<string, mixed>>
     */
    public function locations(bool $refresh = false): array
    {
        if ($this->locations !== null && !$refresh) {
            return $this->locations;
        }

        $rows = [];
        $seen = [];
        $page = 1;
        $expected = null;

        do {
            $body = $this->get('/locations/list?page=' . $page);
            $batch = (array) ($body['locations'] ?? []);

            if ($expected === null) {
                $perPage = max(1, (int) ($body['items_per_page'] ?? 100));
                $expected = (int) ceil((int) ($body['total'] ?? count($batch)) / $perPage);
            }

            foreach ($batch as $row) {
                $tag = $row['tag'] ?? null;

                // A repeated page must not double the list.
                if ($tag !== null && isset($seen[$tag])) {
                    continue;
                }

                $seen[$tag] = true;
                $rows[] = (array) $row;
            }

            $page++;
        } while ($batch !== [] && $page <= min($expected, self::MAX_PAGES));

        return $this->locations = $rows;
    }

    /** One location in full, including the do/linode/vultr region priorities. */
    public function location(string $tag): array
    {
        return $this->get('/locations/' . rawurlencode($tag));
    }

    /**
     * Create a location. `POST /v0/locations/new`.
     *
     * A location starts with no tunnels, so `total` is 0 and checkout will not offer it
     * until tunnels are attached on the panel — creating one here cannot put a region the
     * business cannot serve in front of a customer.
     */
    public function createLocation(array $data): array
    {
        return $this->post('/locations/new', $data);
    }

    /** `POST /v0/locations/update/{tag}`. The tag itself is immutable panel-side. */
    public function updateLocation(string $tag, array $data): array
    {
        return $this->post('/locations/update/' . rawurlencode($tag), $data);
    }

    /** `GET /v0/locations/delete/{tag}`. */
    public function deleteLocation(string $tag): array
    {
        return $this->get('/locations/delete/' . rawurlencode($tag));
    }

    /** `GET /v0/locations/status/{tag}/{enabled|disabled}`. */
    public function setLocationStatus(string $tag, bool $enabled): array
    {
        return $this->get('/locations/status/' . rawurlencode($tag) . '/' . ($enabled ? 'enabled' : 'disabled'));
    }

    // ── Tunnels ──────────────────────────────────────────────────────────────
    //
    // `list` works. The two per-tunnel GET routes are documented with a `/class/{class}`
    // segment that the panel no longer routes, so both of them fall back — see each method.
    // `tunnelsAvailable()` is still what the admin page asks before offering any of this.

    /**
     * Pages until an empty batch rather than trusting `total_pages`, which under-reported on
     * `locations/list` for months. Page sizes also differ per endpoint (200 here, 100 there).
     * Memoised because {@see tunnel()} reads from it.
     *
     * @return array<int, array<string, mixed>>
     */
    public function tunnels(): array
    {
        if ($this->tunnelCache !== null) {
            return $this->tunnelCache;
        }

        $rows = [];
        $page = 1;

        do {
            $body = $this->get('/tunnels/list?page=' . $page);
            $batch = (array) ($body['tunnels'] ?? []);
            $rows = array_merge($rows, array_map(fn ($r) => (array) $r, $batch));
            $page++;
        } while ($batch !== [] && $page <= self::MAX_PAGES);

        return $this->tunnelCache = $rows;
    }

    /**
     * The documented route 404s on the current panel, and no other route returns this row —
     * `GET /tunnels/{id}` gives the provider view, which lacks `location_id`, `service_id`,
     * `email`, `username`, `status` and `tag`. So the fallback reads the row out of `list`
     * and re-wraps it in the documented envelope. Documented path is tried first, so this
     * reverts to one direct call when the panel restores the route.
     */
    public function tunnel(string $tunnelId, string $class): array
    {
        try {
            return $this->get('/tunnels/' . rawurlencode($tunnelId) . '/class/' . rawurlencode($class));
        } catch (PanelHttpException $e) {
            if (!$e->isNotFound()) {
                throw $e;
            }
        }

        foreach ($this->tunnels() as $row) {
            // Loose comparison on the id: `list` types it as an int and callers hold a string.
            // Class is only compared when the row carries one, so a panel that stops
            // returning it does not turn every lookup into "not found".
            if ((string) ($row['tunnel_id'] ?? '') !== $tunnelId) {
                continue;
            }

            if (filled($row['class'] ?? null) && (string) $row['class'] !== $class) {
                continue;
            }

            return ['status' => 'ok', 'tunnels' => [$row]];
        }

        throw new PanelHttpException(404, "The panel has no tunnel {$tunnelId} of class {$class}.");
    }

    /**
     * Live detail from the upstream provider. The documented path 404s; the panel serves the
     * same body at `GET /tunnels/{id}`, so the two routes appear to have been folded into one.
     *
     * This reaches the provider, so it can fail per-tunnel — about a third of `NewRoute`
     * tunnels answer `Unable to get tunnel info: 404|`. That is an upstream failure, not a
     * missing route, and is surfaced as the panel error it is.
     */
    public function tunnelInfo(string $tunnelId, string $class): array
    {
        try {
            return $this->get('/tunnels/info/' . rawurlencode($tunnelId) . '/class/' . rawurlencode($class));
        } catch (PanelHttpException $e) {
            if (!$e->isNotFound()) {
                throw $e;
            }
        }

        return $this->get('/tunnels/' . rawurlencode($tunnelId));
    }

    public function createTunnel(array $data): array
    {
        return $this->post('/tunnels/new', $data);
    }

    public function updateTunnel(string $tunnelId, string $class, array $data): array
    {
        return $this->post('/tunnels/update/' . rawurlencode($tunnelId) . '/class/' . rawurlencode($class), $data);
    }

    public function deleteTunnel(string $tunnelId, string $class): array
    {
        return $this->get('/tunnels/delete/' . rawurlencode($tunnelId) . '/class/' . rawurlencode($class));
    }

    /** Panel-side status is `free` or `disabled`, not enabled/disabled as locations use. */
    public function setTunnelStatus(string $tunnelId, string $class, string $status): array
    {
        return $this->get('/tunnels/status/' . rawurlencode($tunnelId) . '/class/' . rawurlencode($class) . '/' . rawurlencode($status));
    }

    /**
     * Whether the panel's tunnel API answers at all.
     *
     * Cheap and cached for the request: the console calls it before rendering, so a broken
     * panel produces one explanatory banner rather than a 500 per row.
     */
    public function tunnelsAvailable(): bool
    {
        static $available = null;

        if ($available !== null) {
            return $available;
        }

        try {
            $this->get('/tunnels/list?page=1');

            return $available = true;
        } catch (\Throwable $e) {
            return $available = false;
        }
    }

    // ── Transport ────────────────────────────────────────────────────────────

    private function get(string $path): array
    {
        return $this->request('get', $path);
    }

    private function post(string $path, array $data): array
    {
        return $this->request('post', $path, $data);
    }

    /**
     * Parallel to `ProxyPanel::request()`, including the two things the live panel does that a
     * naive client gets wrong: an auth failure returns **HTTP 200 with a plain-text body**
     * (so without the non-array check a bad token reads as success), and a server fault
     * returns an HTML Tracy page (so the body is truncated, not pasted into a notification).
     */
    private function request(string $method, string $path, array $data = []): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('The ProxyPanel server has no API URL or token configured.');
        }

        $url = $this->root() . $path;

        $request = Http::withHeaders([
            'Panel' => (string) ($this->config['api_token'] ?? ''),
            'Accept' => 'application/json',
        ])->retry(2, 200, throw: false)->timeout(self::TIMEOUT);

        $response = $method === 'get' ? $request->get($url) : $request->post($url, $data);
        $body = trim($response->body());

        if (!$response->successful()) {
            $detail = $this->summarise($body);
            $this->log('ProxyPanel admin API error', ['path' => $path, 'status' => $response->status(), 'detail' => $detail]);

            throw new PanelHttpException(
                $response->status(),
                'Panel returned HTTP ' . $response->status() . ': ' . $detail,
            );
        }

        $json = $response->json();

        if (!is_array($json)) {
            $detail = $this->summarise($body);
            $this->log('ProxyPanel admin API returned non-JSON', ['path' => $path, 'body' => $detail]);

            throw new \RuntimeException(
                stripos($detail, 'authoriz') !== false
                    ? 'The panel rejected the API token — check Admin → Servers → ProxyPanel.'
                    : 'The panel returned an unexpected response: ' . $detail
            );
        }

        if (($json['status'] ?? 'ok') === 'error') {
            throw new \RuntimeException('Panel: ' . ($json['description'] ?? 'unknown error'));
        }

        return $json;
    }

    /** `api_url` points at `…/v0/services`; locations and tunnels are its siblings. */
    private function root(): string
    {
        $url = rtrim((string) ($this->config['api_url'] ?? ''), '/');

        return preg_replace('#/services$#', '', $url) ?: $url;
    }

    /** An HTML error page is worthless in a toast, so say what it was instead of pasting it. */
    private function summarise(string $body): string
    {
        if ($body === '') {
            return 'empty response';
        }

        if (str_starts_with($body, '<')) {
            return 'an HTML error page (' . strlen($body) . ' bytes) — the panel logged a server-side fault';
        }

        return \Str::limit($body, 200);
    }

    private function log(string $message, array $context): void
    {
        Log::channel('stack')->error('[ProxyPanel] ' . $message, $context);
    }
}
