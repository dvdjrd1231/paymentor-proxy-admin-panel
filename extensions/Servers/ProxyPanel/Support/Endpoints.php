<?php

namespace Paymenter\Extensions\Servers\ProxyPanel\Support;

use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Where a service's provisioned proxies live.
 *
 * They used to be one comma-joined string in a `properties` row. That column is `TEXT`
 * (65,535 bytes ≈ 1,213 endpoints) and the smallest product in the catalogue sells 1,500
 * ports, so storing the panel's reply threw `Data too long for column 'value'` and the whole
 * provisioning run failed — after the panel had already allocated the proxies. See the
 * migration for the full reasoning.
 *
 * Two things this has to keep doing, which is why it is a class and not a query:
 *
 *  - **Read the old property when the new table has nothing.** Services provisioned before
 *    this change still have their list in `proxy_ips`, and they must keep working.
 *  - **Survive the table not existing.** The migration runs from the extension's
 *    `installed()` hook, so between deploying the code and running it there is a window
 *    where the table is absent. Everything degrades to the legacy property rather than 500s.
 */
class Endpoints
{
    public const LEGACY_PROPERTY = 'proxy_ips';

    /** Rows per INSERT. 31,500 endpoints in one statement exceeds max_allowed_packet. */
    private const CHUNK = 500;

    public static function tableExists(): bool
    {
        static $exists = null;

        return $exists ??= Schema::hasTable('proxypanel_endpoints');
    }

    /**
     * Replace this service's endpoints with the given `host:port` strings.
     *
     * Delete-then-insert rather than a diff: the panel's reply is the whole truth about what
     * a service owns, and a rotation can change every entry at once.
     *
     * @param  array<int, string>  $endpoints
     */
    public static function replace(Service $service, array $endpoints): void
    {
        if (!static::tableExists()) {
            return;
        }

        $rows = [];

        foreach ($endpoints as $endpoint) {
            [$host, $port] = static::split((string) $endpoint);

            if ($host === null) {
                continue;
            }

            $rows[] = ['service_id' => $service->id, 'host' => $host, 'port' => $port ?? 0];
        }

        DB::transaction(function () use ($service, $rows) {
            DB::table('proxypanel_endpoints')->where('service_id', $service->id)->delete();

            foreach (array_chunk($rows, self::CHUNK) as $chunk) {
                DB::table('proxypanel_endpoints')->insert($chunk);
            }
        });
    }

    /**
     * Every endpoint for a service as `host:port`, newest storage first, legacy second.
     *
     * @return array<int, string>
     */
    public static function all(Service $service, ?int $limit = null): array
    {
        if (static::tableExists()) {
            $query = DB::table('proxypanel_endpoints')
                ->where('service_id', $service->id)
                ->orderBy('id');

            if ($limit !== null) {
                $query->limit($limit);
            }

            $rows = $query->get(['host', 'port']);

            if ($rows->isNotEmpty()) {
                return $rows->map(fn ($r) => $r->host . ':' . $r->port)->all();
            }
        }

        $legacy = static::legacy($service);

        return $limit === null ? $legacy : array_slice($legacy, 0, $limit);
    }

    public static function count(Service $service): int
    {
        if (static::tableExists()) {
            $count = DB::table('proxypanel_endpoints')->where('service_id', $service->id)->count();

            if ($count > 0) {
                return $count;
            }
        }

        return count(static::legacy($service));
    }

    /**
     * Walk every endpoint in batches.
     *
     * The export for a 31,500-proxy service is about 1.7 MB of text; building it from a
     * single `get()` would hold every row and every rendered line in memory at once.
     *
     * @param  callable(array<int, string>): void  $callback
     */
    public static function each(Service $service, callable $callback): void
    {
        if (static::tableExists() && static::count($service) > 0) {
            DB::table('proxypanel_endpoints')
                ->where('service_id', $service->id)
                ->orderBy('id')
                ->chunk(self::CHUNK, function ($rows) use ($callback) {
                    $callback($rows->map(fn ($r) => $r->host . ':' . $r->port)->all());
                });

            return;
        }

        foreach (array_chunk(static::legacy($service), self::CHUNK) as $chunk) {
            $callback($chunk);
        }
    }

    public static function forget(Service $service): void
    {
        if (static::tableExists()) {
            DB::table('proxypanel_endpoints')->where('service_id', $service->id)->delete();
        }
    }

    /**
     * The pre-migration storage: a comma-joined list on the service's `proxy_ips` property.
     *
     * @return array<int, string>
     */
    private static function legacy(Service $service): array
    {
        $raw = DB::table('properties')
            ->where('model_type', Service::class)
            ->where('model_id', $service->id)
            ->where('key', self::LEGACY_PROPERTY)
            ->value('value');

        if (!$raw) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $raw))));
    }

    /**
     * Split `host:port`, coping with IPv6.
     *
     * `2a10:500::1:10000` is mostly colons, so the port is the last colon-separated segment
     * and only when it is numeric — the same rule the export uses. A bracketed form
     * (`[2a10:500::1]:10000`) is unwrapped first.
     *
     * @return array{0: string|null, 1: int|null}
     */
    public static function split(string $endpoint): array
    {
        $endpoint = trim($endpoint);

        if ($endpoint === '') {
            return [null, null];
        }

        if (preg_match('/^\[(.+)\]:(\d+)$/', $endpoint, $m)) {
            return [$m[1], (int) $m[2]];
        }

        $pos = strrpos($endpoint, ':');

        if ($pos !== false && ctype_digit(substr($endpoint, $pos + 1))) {
            return [substr($endpoint, 0, $pos), (int) substr($endpoint, $pos + 1)];
        }

        return [$endpoint, null];
    }
}
