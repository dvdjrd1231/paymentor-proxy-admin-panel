<?php

/**
 * End-to-end Cryptomus check.
 *
 * Creates a deposit invoice and hands off to Cryptomus exactly as the checkout does, so the
 * outbound call and the returned payment URL are genuinely theirs.
 *
 * Paying for real means sending crypto to the address on that page, which no script can do.
 * With --settle the callback is delivered instead — over real HTTPS to the live endpoint,
 * signed with the account's real payment API key using Cryptomus' own scheme
 * (md5(base64(json) . api_key)). A forgery is rejected first, so acceptance means something.
 *
 *   php scripts/test-cryptomus-payment.php [amount] [--settle]
 */
$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';
$app = require_once $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Helpers\ExtensionHelper;
use App\Models\Credit;
use App\Models\Gateway;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

$amount = (float) ($argv[1] ?? 5);
$steps = [];

function step(string $label, bool $ok, string $detail = ''): bool
{
    global $steps;
    $steps[] = $ok;
    printf("[ %s ] %-46s %s%s", $ok ? 'PASS' : 'FAIL', $label, $detail, PHP_EOL);

    return $ok;
}

$gateway = Gateway::where('extension', 'Cryptomus')->first();
if (!$gateway) {
    echo "No Cryptomus gateway configured (Admin → Gateways).\n";
    exit(1);
}

$settings = $gateway->settings->pluck('value', 'key');
$merchant = (string) ($settings['merchant_id'] ?? '');
$apiKey = (string) ($settings['payment_api_key'] ?? '');

step('merchant UUID configured', $merchant !== '', $merchant);
step('payment API key configured', $apiKey !== '', '<' . strlen($apiKey) . ' chars>');

// ── 1. Deposit invoice, exactly as the client area creates it ────────────────────────────
$user = User::create([
    'first_name' => 'Cryptomus',
    'last_name' => 'E2E',
    'email' => 'cm-e2e-' . Str::random(8) . '@example.test',
    'password' => bcrypt(Str::random(32)),
]);

$invoice = Invoice::create([
    'user_id' => $user->id,
    'currency_code' => config('settings.default_currency', 'USD'),
    'due_at' => now(),
]);
$invoice->items()->create([
    'description' => __('account.credit_deposit', ['currency' => $invoice->currency_code]),
    'quantity' => 1,
    'price' => $amount,
    'reference_type' => Credit::class,
]);
$invoice->refresh();
step('deposit invoice created', $invoice->exists, sprintf('#%s for %s %s', $invoice->number ?? $invoice->id, $invoice->total, $invoice->currency_code));

// ── 2. Hand off to Cryptomus ─────────────────────────────────────────────────────────────
$checkout = null;
try {
    $checkout = ExtensionHelper::pay($gateway, $invoice);
    $ok = is_string($checkout) ? str_contains($checkout, 'cryptomus') : ($checkout !== null);
    $detail = is_string($checkout) ? $checkout : gettype($checkout);
} catch (Throwable $e) {
    $ok = false;
    $detail = substr($e->getMessage(), 0, 160);
}
step('payment created at Cryptomus', $ok, $detail);

if (!$ok) {
    echo "\nCryptomus refused the hand-off — check the merchant UUID and API key.\n";
    exit(1);
}

$pending = $invoice->transactions()->latest('id')->first();
step('pending transaction recorded', $pending !== null, $pending?->transaction_id ?? 'none');

// ── 3. Settlement ────────────────────────────────────────────────────────────────────────
if (in_array('--settle', $argv, true)) {
    echo PHP_EOL . 'Delivering a signed callback to the live endpoint…' . PHP_EOL;

    $url = route('extensions.gateways.cryptomus.webhook');
    $uuid = $pending?->transaction_id ?: (string) Str::uuid();

    $deliver = function (string $status) use ($url, $apiKey, $uuid, $invoice, $amount) {
        $payload = [
            'type' => 'payment',
            'uuid' => $uuid,
            'order_id' => (string) $invoice->id,
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => $invoice->currency_code,
            'status' => $status,
        ];
        // Cryptomus signs the JSON body base64'd and concatenated with the API key.
        $payload['sign'] = md5(base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES)) . $apiKey);

        return Http::timeout(30)->asJson()->post($url, $payload);
    };

    $forged = Http::timeout(30)->asJson()->post($url, [
        'type' => 'payment', 'uuid' => $uuid, 'order_id' => (string) $invoice->id,
        'amount' => number_format($amount, 2, '.', ''), 'currency' => $invoice->currency_code,
        'status' => 'paid', 'sign' => str_repeat('0', 32),
    ]);
    step('forged sign refused over HTTPS', $forged->status() === 400, 'HTTP ' . $forged->status());

    $invoice->refresh();
    step('invoice untouched by the forgery', $invoice->status !== 'paid', 'status=' . $invoice->status);

    $paid = $deliver('paid');
    step('signed callback accepted over HTTPS', $paid->successful(), 'HTTP ' . $paid->status());

    sleep(2);
    $invoice->refresh();
    step('invoice settled', $invoice->status === 'paid', 'status=' . $invoice->status);

    $rows = fn () => $invoice->transactions()->where('transaction_id', $uuid)->count();
    step('transaction recorded against the invoice', $rows() > 0, 'rows=' . $rows());

    $credit = $user->credits()->where('currency_code', $invoice->currency_code)->first();
    step('credit balance applied', $credit && (float) $credit->amount === $amount, $credit?->formatted_amount ?? 'none');

    // Cryptomus retries callbacks; a redelivery must not bank the money twice.
    $before = $rows();
    $deliver('paid');
    sleep(2);
    step('redelivered callback does not double-credit', $rows() === $before, 'rows=' . $rows());

    $credit?->refresh();
    step('balance unchanged after redelivery', $credit && (float) $credit->amount === $amount, $credit?->formatted_amount ?? '-');
}

// ── Result ───────────────────────────────────────────────────────────────────────────────
$failed = count(array_filter($steps, fn ($s) => !$s));
printf('%s%d of %d checks passed%s', PHP_EOL, count($steps) - $failed, count($steps), PHP_EOL);
echo 'Test user : ' . $user->email . PHP_EOL;
echo 'Invoice   : #' . ($invoice->number ?? $invoice->id) . PHP_EOL;
echo 'Checkout  : ' . (is_string($checkout) ? $checkout : '(view)') . PHP_EOL;

exit($failed === 0 ? 0 : 1);
