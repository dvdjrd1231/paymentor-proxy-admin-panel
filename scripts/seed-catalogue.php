<?php

/**
 * Build the proxy catalogue from the client's real, published product line.
 *
 * Prices and tiers are taken from the live store at my.noxproxy.com/store — the products
 * they actually sell today — rather than invented. Five IPv6 residential tiers, each in
 * HTTP and Socks5h, with Socks5h priced at exactly twice HTTP across every tier.
 *
 * What this deliberately does NOT set is the panel `plan` tag. The panel offers plans on a
 * different axis (bandwidth: 1G/2G/4G, backend: Squid/3Proxy) from the commercial line
 * (port count: 1,500 → 31,500), so only the client can say which panel plan backs which
 * product. Provisioning with the wrong tag would deliver the wrong service, which is worse
 * than not provisioning — an unmapped product fails fast with
 * "ProxyPanel: no Plan configured on this product."
 *
 *   php scripts/seed-catalogue.php            # show what it would do
 *   php scripts/seed-catalogue.php --apply    # write it
 */
$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';
$app = require_once $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Category;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Server;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

$apply = in_array('--apply', $argv, true);

/** The live product line: tier => [ports, HTTP price USD]. */
const TIERS = [
    'Amethyst' => [1500, 70.00],
    'Emerald' => [4500, 120.00],
    'Jade' => [13500, 350.00],
    'Onyx' => [22500, 580.00],
    'Ruby' => [31500, 800.00],
];

/** Socks5h is twice the HTTP price in every tier on the public store. */
const SOCKS5_MULTIPLIER = 2;

echo $apply ? "Applying.\n\n" : "Dry run — nothing will be written. Re-run with --apply.\n\n";

$server = Server::where('extension', 'ProxyPanel')->first();
if (!$server) {
    echo "No ProxyPanel server configured.\n";
    exit(1);
}

$settings = $server->settings->pluck('value', 'key');
$panel = rtrim((string) $settings['api_url'], '/');
$headers = ['Panel' => (string) $settings['api_token'], 'Accept' => 'application/json'];

$planTags = array_values(array_filter((array) Http::withHeaders($headers)->timeout(25)->get($panel . '/plans')->json(), 'is_string'));
$locations = (array) Http::withHeaders($headers)->timeout(25)->get($panel . '/locations')->json();

$category = Category::firstOrCreate(
    ['name' => 'Proxies'],
    ['slug' => 'proxies', 'description' => 'IPv6 residential proxy plans.'],
);

// ── Retire the earlier placeholder products, but never one that has been sold ────────────
$stale = Product::where('slug', 'like', 'proxy-%')->get()
    ->filter(fn ($p) => !Service::where('product_id', $p->id)->exists());

printf("[ %s ] retire %d placeholder product(s)%s", $apply ? ' ok ' : 'todo', $stale->count(), PHP_EOL);

if ($apply) {
    foreach ($stale as $p) {
        $planIds = DB::table('plans')->where('priceable_type', Product::class)->where('priceable_id', $p->id)->pluck('id');
        DB::table('prices')->whereIn('plan_id', $planIds)->delete();
        DB::table('plans')->whereIn('id', $planIds)->delete();
        DB::table('settings')->where('settingable_type', Product::class)->where('settingable_id', $p->id)->delete();
        $p->delete();
    }
}

echo PHP_EOL;

// ── The real product line ────────────────────────────────────────────────────────────────
foreach (TIERS as $tier => [$ports, $httpPrice]) {
    $variants = [
        'HTTP Proxy' => ['http', $httpPrice],
        'Socks5h' => ['socks5', $httpPrice * SOCKS5_MULTIPLIER],
    ];

    foreach ($variants as $label => [$protocol, $price]) {
        $name = sprintf('IPv6 Residential %s - %s - M', $tier, $label);
        $slug = Str::slug($name);
        $existing = Product::where('slug', $slug)->first();

        printf("[ %s ] %-46s %7s ports  USD %s%s",
            $existing ? 'sync' : ($apply ? ' ok ' : 'todo'), $name, number_format($ports), number_format($price, 2), PHP_EOL);

        if (!$apply) {
            continue;
        }

        $product = $existing ?: Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => $slug,
            'description' => sprintf(
                '%s residential proxies — %s ports, private proxy server, rotating or static, IP or user/password authentication.',
                $label, number_format($ports),
            ),
            'server_id' => $server->id,
            'allow_quantity' => 'combined',
            'hidden' => false,
        ]);

        // Everything the ProxyPanel module needs except `plan`, which only the client can map.
        foreach ([
            'amount' => (string) $ports,
            'protocol' => $protocol,
            'allow_rotation' => 'yes',
            'change_rotation' => 'yes',
            'auth_ips' => '5',
            'amount_rotations' => '100',
            'bwlimit' => '',
        ] as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['settingable_type' => Product::class, 'settingable_id' => $product->id, 'key' => $key],
                ['value' => $value, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        // priceable_type is not mass-assignable on Plan, so go through the query builder.
        $planId = DB::table('plans')->where('priceable_type', Product::class)
            ->where('priceable_id', $product->id)->value('id');

        if (!$planId) {
            $planId = DB::table('plans')->insertGetId([
                'name' => 'Monthly',
                'priceable_type' => Product::class,
                'priceable_id' => $product->id,
                'type' => 'recurring',
                'billing_period' => 1,
                'billing_unit' => 'month',
                'sort' => 0,
            ]);
        }

        // Never overwrite a price already adjusted in the admin.
        if (!DB::table('prices')->where('plan_id', $planId)->where('currency_code', 'USD')->exists()) {
            DB::table('prices')->insert([
                'plan_id' => $planId, 'currency_code' => 'USD',
                'price' => $price, 'setup_fee' => 0,
            ]);
        }
    }
}

echo PHP_EOL;
echo "Prices are the client's own published USD prices (my.noxproxy.com/store).\n";

if (Currency::where('code', 'BRL')->exists()) {
    echo "BRL is registered but deliberately left unpriced — the live store sells in USD only,\n";
    echo "so any BRL figure would be invented. Add BRL prices in the admin if BRL selling is wanted.\n";
}

echo PHP_EOL;
echo "STILL TO MAP: each product needs its panel `plan` tag set in Admin -> Products.\n";
printf("  panel plans available (%d): %s%s", count($planTags), implode(', ', $planTags), PHP_EOL);
echo "  Until a product has a plan tag, provisioning fails fast with a clear error rather\n";
echo "  than delivering the wrong service.\n";

if (!$locations) {
    echo PHP_EOL . "NOTE: the panel still lists no locations, so provisioning cannot complete yet.\n";
}

if (!$apply) {
    echo PHP_EOL . "Nothing was written. Re-run with --apply.\n";
}
