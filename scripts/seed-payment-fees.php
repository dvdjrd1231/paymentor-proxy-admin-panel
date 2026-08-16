<?php

/**
 * Pre-fill payment fee rules from each provider's published merchant rate.
 *
 * Rules are created **inactive**. Nothing is charged to any customer until someone
 * switches a rule on in Admin → Payment Fees. Whether to pass processing costs on to the
 * buyer at all is a commercial decision, so this only removes the data-entry step and
 * leaves the decision where it belongs.
 *
 * Published rates used (see docs/modules/payment-fees.md for sources):
 *
 *   Stripe        2.9% + $0.30   standard published card rate
 *   CoinPayments  0.5%           coins; 1% for stablecoins and tokens
 *   Cryptomus     0.4%           entry tier; rises to 2% on some merchant tiers
 *   Binance Pay   0%             no merchant fee to receive; payouts are charged separately
 *
 * Re-running never overwrites a rule that has been edited or enabled.
 *
 *   php scripts/seed-payment-fees.php            # show what it would do
 *   php scripts/seed-payment-fees.php --apply    # write it
 */
$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';
$app = require_once $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Gateway;
use Illuminate\Support\Facades\DB;

$apply = in_array('--apply', $argv, true);

/** gateway extension => [fee_type, fixed, percent, note] */
const PUBLISHED_RATES = [
    'Stripe' => ['both', 0.30, 2.9, 'Published card rate 2.9% + $0.30'],
    'CoinPayments' => ['percent', 0.00, 0.5, 'Published 0.5% for coins (1% stablecoins/tokens)'],
    'Cryptomus' => ['percent', 0.00, 0.4, 'Published entry tier 0.4% (up to 2% by tier)'],
    'Binance' => ['percent', 0.00, 0.0, 'No merchant fee to receive; payouts charged separately'],
];

echo $apply ? "Applying (rules are created INACTIVE).\n\n" : "Dry run — nothing will be written. Re-run with --apply.\n\n";

foreach (PUBLISHED_RATES as $extension => [$type, $fixed, $percent, $note]) {
    $gateway = Gateway::where('extension', $extension)->first();

    if (!$gateway) {
        printf("[ skip ] %-14s gateway not configured%s", $extension, PHP_EOL);
        continue;
    }

    $name = $extension . ' processing cost';
    $existing = DB::table('payment_fee_rules')->where('name', $name)->first();

    $state = $existing
        ? ($existing->active ? 'live' : 'exists')
        : ($apply ? ' ok ' : 'todo');

    printf("[ %s ] %-14s %-7s %5.2f%% + %.2f   %s%s",
        $state, $extension, $type, $percent, $fixed, $note, PHP_EOL);

    if ($existing || !$apply) {
        continue;
    }

    DB::table('payment_fee_rules')->insert([
        'name' => $name,
        'gateway' => $gateway->name,
        'fee_type' => $type,
        'fixed_amount' => $fixed,
        'percent_amount' => $percent,
        'country' => null,
        'currency_code' => null,
        'product_id' => null,
        'customer_type' => null,
        'min_amount' => null,
        'max_amount' => null,
        'priority' => 100,
        'active' => false,        // deliberately off — enabling charges real customers
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

echo PHP_EOL;
echo "All rules are INACTIVE. No customer is charged a fee until one is enabled in\n";
echo "Admin -> Payment Fees. Passing processing costs on to the buyer is a commercial\n";
echo "decision, so the figures are prepared but the switch is left to the client.\n";

if (!$apply) {
    echo PHP_EOL . "Nothing was written. Re-run with --apply.\n";
}
