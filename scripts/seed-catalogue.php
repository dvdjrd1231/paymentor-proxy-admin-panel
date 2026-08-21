<?php

/**
 * Build the proxy catalogue from the client's real, published product line.
 *
 * Prices and tiers are taken from the live store at my.noxproxy.com/store — the products
 * they actually sell today — rather than invented. Five IPv6 residential tiers, each in
 * HTTP and Socks5h, with Socks5h priced at exactly twice HTTP across every tier.
 *
 * The panel `plan` tag mapping is settled — see PANEL_PLAN below for the reasoning and the
 * one part of it that is a judgement rather than a fact.
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

/** IPv4 tiers shown by the reference storefront. */
const IPV4_TIERS = [
    '/26 (Dedicated)' => [64, 190.00, '1G'],
    '/25 (Dedicated)' => [128, 350.00, '2G'],
    '/24 (Dedicated)' => [256, 640.00, '4G'],
];

/**
 * Panel plan tag per bandwidth tier and protocol.
 *
 * Protocol is a fact, not a choice: Squid is an HTTP-only daemon and the panel publishes no
 * `Squid-S5` plan, so every Socks5h product must run on a 3Proxy `-S5` tag.
 *
 * 3Proxy is used for the HTTP products as well, which is why the three `*-Squid-HT` plans
 * are deliberately unused. One daemon serves both protocols, so a tier's HTTP and Socks5h
 * variants behave identically apart from the protocol, and support has one implementation
 * to reason about instead of two. Squid would add a second daemon for no capability the
 * line needs. Switching a product back to Squid is a single field in Admin → Products if
 * that turns out to be wrong.
 *
 * The bandwidth column in TIERS is the one judgement: the store sells five tiers by port
 * count while the panel offers three by bandwidth, so it cannot be one to one. The pairing
 * is monotonic — more ports never receives less bandwidth — with Amethyst and Emerald on
 * 1G, Jade on 2G, and Onyx and Ruby on 4G.
 */
const PANEL_PLAN = [
    '1G' => ['http' => '1GP-3Proxy-HT', 'socks5' => '1GP-3Proxy-S5'],
    '2G' => ['http' => '2GP-3Proxy-HT', 'socks5' => '2GP-3Proxy-S5'],
    '4G' => ['http' => '4GP-3Proxy-HT', 'socks5' => '4GP-3Proxy-S5'],
];

/** Socks5h is twice the HTTP price in every tier on the public store. */
const SOCKS5_MULTIPLIER = 2;

/** Public IPv6 catalogue periods, with USD HTTP prices from the reference store. */
const PERIODS = [
    'monthly' => [
        'category' => ['IPv6 Proxy Monthly Plans', 'ipv6-proxy-monthly-plans', 'Monthly subscription renewable IPv6 residential proxy plans.'],
        'suffix' => 'M',
        'type' => 'recurring',
        'billing_period' => 1,
        'billing_unit' => 'month',
        'prices' => [70, 120, 350, 580, 800],
    ],
    'weekly' => [
        'category' => ['IPv6 Proxy Weekly Plans', 'ipv6-proxy-weekly-plans', 'One time payment IPv6 residential proxy plans for seven days.'],
        'suffix' => 'W',
        'type' => 'one-time',
        'billing_period' => 1,
        'billing_unit' => 'week',
        'prices' => [28, 35, 91, 154, 196],
    ],
    'daily' => [
        'category' => ['IPv6 Proxy Daily Plans', 'ipv6-proxy-daily-plans', 'One time payment IPv6 residential proxy plans for one day.'],
        'suffix' => 'D',
        'type' => 'one-time',
        'billing_period' => 1,
        'billing_unit' => 'day',
        'prices' => [4, 5, 13, 22, 28],
    ],
    'ipv4-monthly' => [
        'category' => ['IPv4 Proxy Monthly Plans', 'ipv4-proxy-monthly-plans', 'Dedicated IPv4 residential proxy plans billed monthly.'],
        'suffix' => 'I4M',
        'type' => 'recurring',
        'billing_period' => 1,
        'billing_unit' => 'month',
        'tiers' => IPV4_TIERS,
    ],
];

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

$categories = [];
foreach (PERIODS as $period) {
    [$name, $slug, $description] = $period['category'];
    $category = Category::where('slug', $slug)->first();

    // Preserve the original monthly category and its product/service relationships.
    if (!$category && $slug === 'ipv6-proxy-monthly-plans') {
        $category = Category::where('slug', 'proxies')->first();
    }

    if (!$category) {
        $category = $apply
            ? Category::create(['name' => $name, 'slug' => $slug, 'description' => $description])
            : Category::make(['name' => $name, 'slug' => $slug, 'description' => $description]);
    }

    $categories[$period['suffix']] = $category;
}

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
foreach (PERIODS as $period) {
    $category = $categories[$period['suffix']];
    $tiers = $period['tiers'] ?? TIERS;

    $tierNames = array_keys($tiers);

    foreach ($tiers as $tier => [$ports, $httpPrice, $bandwidth]) {
        // `prices` is a positional list (one entry per tier, in TIERS order) while $tier is
        // the tier *name*, so `$period['prices'][$tier]` never matched and every period
        // silently fell back to the monthly price: weekly and daily plans would have been
        // created at $70 instead of $28 and $4. Monthly only looked correct because its
        // list repeats the same figures TIERS already carries.
        $tierIndex = array_search($tier, $tierNames, true);
        $periodPrice = $period['prices'][$tierIndex] ?? $httpPrice;
        $variants = isset($period['tiers'])
            ? ['HTTP Proxy' => ['http', $periodPrice]]
            : [
                'HTTP Proxy' => ['http', $periodPrice],
                'Socks5h' => ['socks5', $periodPrice * SOCKS5_MULTIPLIER],
            ];

        foreach ($variants as $label => [$protocol, $price]) {
            $family = isset($period['tiers']) ? 'IPv4 Residential' : 'IPv6 Residential';
            $name = isset($period['tiers'])
                ? sprintf('%s %s - M', $family, $tier)
                : sprintf('%s %s - %s - %s', $family, $tier, $label, $period['suffix']);
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

        // Feature bullets, worded and ordered as the reference storefront lists them.
        //
        // %s throughout: this used to carry a `%,d` specifier, which PHP does not have —
        // it is Java's grouping flag — so sprintf threw "Unknown format specifier ','" and
        // --apply aborted on the first product it tried to create. The thousands separator
        // is applied with number_format instead, using '.' to match the reference ("1.500
        // HTTP Proxy Ports").
        $description = sprintf(
            '<ul><li>Anonymous Residential %s Proxy</li><li>%s %s Ports</li>'
            . '<li>Private Proxy Server</li><li>Rotating Proxies or Static Proxies</li>'
            . '<li>IP Whitelist Authentication</li><li>User/Password Authentication</li>'
            . '<li>Up-To %d IP whitelist</li><li>Configurable IP Proxies rotation time</li></ul>',
            str_contains($family, 'IPv4') ? 'IPv4' : 'IPv6',
            number_format($ports, 0, ',', '.'),
            $protocol === 'socks5' ? 'Socks5h' : 'HTTP Proxy',
            match (true) {
                $ports <= 1500 => 5,
                $ports <= 4500 => 7,
                $ports <= 13500 => 10,
                $ports <= 22500 => 15,
                default => 20,
            },
        );

            $product = $existing ?: Product::create([
                'category_id' => $category->id,
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'server_id' => $server->id,
                'allow_quantity' => 'combined',
                'hidden' => false,
            ]);

        // Everything the ProxyPanel module needs to provision this product.
        //
        // The plan tag is written whether or not the panel confirmed it. It is computed
        // from PANEL_PLAN, which is a fixed mapping, so it is already the right answer;
        // querying the panel only sanity-checks it, and an unreachable panel (or one
        // answering in a different shape, as the mock does) is not evidence the tag is
        // wrong. Blanking it on a failed check overwrote every live product's correct tag
        // with an empty string and silently broke provisioning for the whole catalogue.
        // The "(NOT on panel!)" warning above still flags anything unconfirmed.
            foreach ([
            'plan' => $planTag,
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
                    'name' => ucfirst($period['type'] === 'recurring' ? 'Monthly' : $period['billing_unit']),
                'priceable_type' => Product::class,
                'priceable_id' => $product->id,
                    'type' => $period['type'],
                    'billing_period' => $period['billing_period'],
                    'billing_unit' => $period['billing_unit'],
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
}

echo PHP_EOL;
echo "Prices are the client's own published USD prices (my.noxproxy.com/store).\n";

if (Currency::where('code', 'BRL')->exists()) {
    $brlPrices = DB::table('prices')->where('currency_code', 'BRL')->count();
    if ($brlPrices === 0) {
        echo "WARNING: BRL is registered but no product has a BRL price. A customer who selects\n";
        echo "BRL then cannot buy anything. Add BRL prices in Admin -> Products, or remove the\n";
        echo "currency until the BRL decision is made.\n";
    }
} else {
    echo "BRL is not registered. The live store sells in USD only, and a currency with no prices\n";
    echo "makes every product unbuyable for whoever selects it — so a currency is added together\n";
    echo "with its prices, never before them.\n";
}

echo PHP_EOL;
echo "PLAN TAGS — protocol is a fact, bandwidth is a judgement.\n";
echo "Squid is HTTP-only, so Socks5h must use a 3Proxy -S5 plan. 3Proxy is used for HTTP too,\n";
echo "so the *-Squid-HT plans are deliberately unused: one daemon serves both protocols.\n";
echo "The bandwidth pairing is monotonic; revisit it if the tiers do not match real capacity:
\n";

foreach (TIERS as $t => [$p, , $bw]) {
    printf("  %-9s %7s ports  ->  %-14s %s%s", $t, number_format($p),
        PANEL_PLAN[$bw]['http'], PANEL_PLAN[$bw]['socks5'], PHP_EOL);
}

printf("%s  panel plans available (%d): %s%s", PHP_EOL, count($planTags), implode(', ', $planTags), PHP_EOL);
echo "  Unused by this mapping: the Squid variants (1GB/2GB/4GB-Squid-HT).\n";

if (!$locations) {
    echo PHP_EOL . "NOTE: the panel still lists no locations, so provisioning cannot complete yet.\n";
}

if (!$apply) {
    echo PHP_EOL . "Nothing was written. Re-run with --apply.\n";
}
