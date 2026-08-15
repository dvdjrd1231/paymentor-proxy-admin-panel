<?php

/**
 * Build the proxy catalogue from the plans the panel actually offers.
 *
 * The shipped catalogue is Paymenter demo data — a single "IPv6Proxy Test" product in a
 * "VPS Hosting" category, with plans named "Test Payment" and "Test Premium". This replaces
 * it with products mirroring the real plan tags returned by the panel's /plans endpoint, so
 * nothing here is invented: the names, and the `plan` tag each product sends when
 * provisioning, come from the panel itself.
 *
 * It also registers BRL alongside USD. Paymenter has no exchange-rate column — every price
 * is stored per currency — so BRL prices are written explicitly.
 *
 * PRICES ARE PLACEHOLDERS. Only the client can set commercial prices; the figures here are
 * derived mechanically so the store is usable for testing and are meant to be edited in
 * Admin → Products. The script prints a reminder and never overwrites a price that has
 * already been changed.
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
use App\Models\Plan;
use App\Models\Product;
use App\Models\Server;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

$apply = in_array('--apply', $argv, true);

/** Placeholder monthly price per bandwidth tier, in USD. Edit in the admin afterwards. */
const USD_BY_TIER = ['1G' => 9.99, '2G' => 17.99, '4G' => 32.99];

/** Placeholder BRL conversion. Not a live rate — set real BRL prices in the admin. */
const BRL_PER_USD = 5.40;

echo $apply ? "Applying.\n\n" : "Dry run — nothing will be written. Re-run with --apply.\n\n";

// ── The panel is the source of truth for what can be sold ────────────────────────────────
$server = Server::where('extension', 'ProxyPanel')->first();
if (!$server) {
    echo "No ProxyPanel server configured.\n";
    exit(1);
}

$settings = $server->settings->pluck('value', 'key');
$panel = rtrim((string) $settings['api_url'], '/');

$plansResponse = Http::withHeaders(['Panel' => (string) $settings['api_token'], 'Accept' => 'application/json'])
    ->timeout(25)->get($panel . '/plans');
$planTags = array_values(array_filter((array) $plansResponse->json(), 'is_string'));

if (!$planTags) {
    echo "The panel returned no plans — cannot build a catalogue from it.\n";
    exit(1);
}
printf("Panel offers %d plans: %s%s%s", count($planTags), implode(', ', $planTags), PHP_EOL, PHP_EOL);

$locations = (array) Http::withHeaders(['Panel' => (string) $settings['api_token'], 'Accept' => 'application/json'])
    ->timeout(25)->get($panel . '/locations')->json();

if (!$locations) {
    echo "NOTE: the panel lists no locations. Products can be created and sold, but\n";
    echo "      provisioning will fail with \"Requested location not available\" until a\n";
    echo "      location exists on the panel. That is panel-side configuration.\n\n";
}

// ── Currency ─────────────────────────────────────────────────────────────────────────────
$hasBrl = Currency::where('code', 'BRL')->exists();
printf("[ %s ] BRL currency%s", $hasBrl ? 'skip' : ($apply ? ' ok ' : 'todo'), PHP_EOL);

if (!$hasBrl && $apply) {
    Currency::create(['code' => 'BRL', 'name' => 'Brazilian Real', 'prefix' => 'R$', 'suffix' => '', 'format' => '1.000,00']);
}

// ── Category ─────────────────────────────────────────────────────────────────────────────
$category = Category::where('name', 'Proxies')->first();
printf("[ %s ] \"Proxies\" category%s", $category ? 'skip' : ($apply ? ' ok ' : 'todo'), PHP_EOL);

if (!$category && $apply) {
    $category = Category::create(['name' => 'Proxies', 'slug' => 'proxies', 'description' => 'IPv6 and IPv4 proxy plans.']);
}

echo PHP_EOL;

// ── One product per panel plan ───────────────────────────────────────────────────────────
foreach ($planTags as $tag) {
    // Tags look like "2GP-3Proxy-S5": bandwidth tier, backend, protocol.
    $tier = strtoupper(substr($tag, 0, 2));
    $usd = USD_BY_TIER[$tier] ?? 9.99;
    $brl = round($usd * BRL_PER_USD, 2);
    $protocol = str_contains($tag, 'S5') ? 'socks5' : 'http';
    $name = 'Proxy ' . $tag;
    $slug = Str::slug($name);

    $existing = Product::where('slug', $slug)->first();
    printf("[ %s ] %-22s  plan_tag=%-16s %s USD %.2f / BRL %.2f%s",
        $existing ? 'sync' : ($apply ? ' ok ' : 'todo'), $name, $tag, $protocol, $usd, $brl, PHP_EOL);

    if (!$apply) {
        continue;
    }

    // Each step is repaired independently, so a half-finished run can simply be re-run.
    $product = $existing ?: Product::create([
        'category_id' => $category->id,
        'name' => $name,
        'slug' => $slug,
        'description' => 'Proxy plan "' . $tag . '" provisioned automatically on the panel.',
        'server_id' => $server->id,
        'allow_quantity' => 'combined',
        'hidden' => false,
    ]);

    // What the ProxyPanel module reads when provisioning. `plan` is the panel's own tag;
    // Region/location is chosen at checkout and is deliberately not hardcoded here.
    foreach ([
        'plan' => $tag,
        'amount' => '1',
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

    // priceable_type is not mass-assignable on Plan, so insert through the query builder.
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

    foreach (['USD' => $usd, 'BRL' => $brl] as $code => $amount) {
        if (!Currency::where('code', $code)->exists()) {
            continue;
        }
        // Never overwrite a price someone has already adjusted in the admin.
        $exists = DB::table('prices')->where('plan_id', $planId)->where('currency_code', $code)->exists();
        if (!$exists) {
            DB::table('prices')->insert([
                'plan_id' => $planId, 'currency_code' => $code,
                'price' => $amount, 'setup_fee' => 0,
            ]);
        }
    }
}

echo PHP_EOL;
echo "Prices above are PLACEHOLDERS derived from bandwidth tier at R\$" . BRL_PER_USD . "/USD.\n";
echo "Set the real commercial prices in Admin → Products before selling.\n";

if (!$apply) {
    echo PHP_EOL . "Nothing was written. Re-run with --apply.\n";
}
