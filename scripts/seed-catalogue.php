<?php

/**
 * Build the proxy catalogue from the client's real, published product line.
 *
 * Prices and tiers are taken from the live store at my.noxproxy.com/store — the products
 * they actually sell today — rather than invented. Five IPv6 residential tiers, each in
 * HTTP and Socks5h, with Socks5h priced at exactly twice HTTP across every tier.
 *
 * The panel `plan` tag is set provisionally. Protocol is certain — Squid is HTTP-only, so
 * a Socks5h product must use a 3Proxy `-S5` plan — but the bandwidth tier is a judgement,
 * because the store sells five tiers by port count while the panel offers three by
 * bandwidth. See PANEL_PLAN below. The client must confirm it before real provisioning.
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

/** The live product line: tier => [ports, HTTP price USD, panel bandwidth tier]. */
const TIERS = [
    'Amethyst' => [1500, 70.00, '1G'],
    'Emerald' => [4500, 120.00, '1G'],
    'Jade' => [13500, 350.00, '2G'],
    'Onyx' => [22500, 580.00, '4G'],
    'Ruby' => [31500, 800.00, '4G'],
];

/**
 * Panel plan tag per bandwidth tier and protocol.
 *
 * Only part of this is derivable. Squid is an HTTP-only daemon — the panel has no
 * `Squid-S5` plan — so every Socks5h product must use a 3Proxy `-S5` tag. That half is
 * certain. 3Proxy is then used for HTTP too, so both variants of a tier run the same
 * backend rather than mixing daemons.
 *
 * The bandwidth column in TIERS is a JUDGEMENT, not a fact: the store sells five tiers by
 * port count while the panel offers three by bandwidth, so the mapping cannot be one to
 * one. It is monotonic — more ports never gets less bandwidth — and must be confirmed by
 * the client before real provisioning. Confirm in Admin → Products.
 */
const PANEL_PLAN = [
    '1G' => ['http' => '1GP-3Proxy-HT', 'socks5' => '1GP-3Proxy-S5'],
    '2G' => ['http' => '2GP-3Proxy-HT', 'socks5' => '2GP-3Proxy-S5'],
    '4G' => ['http' => '4GP-3Proxy-HT', 'socks5' => '4GP-3Proxy-S5'],
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
foreach (TIERS as $tier => [$ports, $httpPrice, $bandwidth]) {
    $variants = [
        'HTTP Proxy' => ['http', $httpPrice],
        'Socks5h' => ['socks5', $httpPrice * SOCKS5_MULTIPLIER],
    ];

    foreach ($variants as $label => [$protocol, $price]) {
        $name = sprintf('IPv6 Residential %s - %s - M', $tier, $label);
        $slug = Str::slug($name);
        $existing = Product::where('slug', $slug)->first();

        $planTag = PANEL_PLAN[$bandwidth][$protocol] ?? '';
        $tagKnown = in_array($planTag, $planTags, true);

        printf("[ %s ] %-46s %7s ports  USD %-9s %s%s",
            $existing ? 'sync' : ($apply ? ' ok ' : 'todo'), $name, number_format($ports),
            number_format($price, 2), $tagKnown ? $planTag : ($planTag . ' (NOT on panel!)'), PHP_EOL);

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

        // Everything the ProxyPanel module needs to provision this product.
        foreach ([
            'plan' => $tagKnown ? $planTag : '',
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
echo "PLAN TAGS — protocol is certain, bandwidth is a judgement.
";
echo "Squid is HTTP-only, so every Socks5h product must use a 3Proxy -S5 plan. The bandwidth
";
echo "tier is not derivable: the store sells 5 tiers by port count, the panel offers 3 by
";
echo "bandwidth. The mapping below is monotonic and NEEDS THE CLIENT'S CONFIRMATION:

";

foreach (TIERS as $t => [$p, , $bw]) {
    printf("  %-9s %7s ports  ->  %-14s %s%s", $t, number_format($p),
        PANEL_PLAN[$bw]['http'], PANEL_PLAN[$bw]['socks5'], PHP_EOL);
}

printf("%s  panel plans available (%d): %s%s", PHP_EOL, count($planTags), implode(', ', $planTags), PHP_EOL);
echo "  Unused by this mapping: the Squid variants (1GB/2GB/4GB-Squid-HT).
";

if (!$locations) {
    echo PHP_EOL . "NOTE: the panel still lists no locations, so provisioning cannot complete yet.\n";
}

if (!$apply) {
    echo PHP_EOL . "Nothing was written. Re-run with --apply.\n";
}
