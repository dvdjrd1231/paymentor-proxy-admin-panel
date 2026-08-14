<?php

/**
 * End-to-end Binance Pay check.
 *
 * Creates a deposit invoice and hands off to Binance Pay exactly as the checkout does, so
 * the signed API call and the returned checkout link are genuinely theirs.
 *
 * Settlement cannot be simulated here, and that is a property of Binance rather than a gap
 * in the test. CoinPayments and Cryptomus sign callbacks with a secret we also hold, so a
 * valid one can be produced locally. Binance signs with **its own RSA private key** and
 * gives merchants only the public certificate — so a genuine callback can only come from a
 * real payment. What is checked instead is that the certificate can be fetched (webhook
 * verification has what it needs) and that an unsigned or forged callback is refused.
 *
 *   php scripts/test-binance-payment.php [amount]
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
    printf("[ %s ] %-48s %s%s", $ok ? 'PASS' : 'FAIL', $label, $detail, PHP_EOL);

    return $ok;
}

$gateway = Gateway::where('extension', 'Binance')->first();
if (!$gateway) {
    echo "No Binance gateway configured (Admin → Gateways).\n";
    exit(1);
}

$settings = $gateway->settings->pluck('value', 'key');
step('API key configured', !empty($settings['api_key']), '<' . strlen((string) ($settings['api_key'] ?? '')) . ' chars>');
step('API secret configured', !empty($settings['api_secret']), '<' . strlen((string) ($settings['api_secret'] ?? '')) . ' chars>');

// ── 1. Deposit invoice, exactly as the client area creates it ────────────────────────────
$user = User::create([
    'first_name' => 'Binance',
    'last_name' => 'E2E',
    'email' => 'bn-e2e-' . Str::random(8) . '@example.test',
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

// ── 2. Signed hand-off to Binance Pay ────────────────────────────────────────────────────
$view = null;
try {
    $view = ExtensionHelper::pay($gateway, $invoice);
    $ok = $view !== null;
    $detail = '';
} catch (Throwable $e) {
    $ok = false;
    $detail = substr($e->getMessage(), 0, 170);
}
step('order created at Binance Pay (request signed)', $ok, $detail);

if (!$ok) {
    echo "\nBinance refused the hand-off. Check the API key/secret and that the merchant\n";
    echo "account is approved for Binance Pay.\n";
    exit(1);
}

// The pay view carries the checkout link Binance returned.
$checkoutUrl = null;
if (is_object($view) && method_exists($view, 'getData')) {
    $checkoutUrl = $view->getData()['checkoutUrl'] ?? null;
}
step('checkout link returned', !empty($checkoutUrl), (string) ($checkoutUrl ?? 'none'));

$pending = $invoice->transactions()->latest('id')->first();
step('pending transaction recorded', $pending !== null, $pending?->transaction_id ?? 'none');

// ── 3. Webhook verification material ─────────────────────────────────────────────────────
// Fetching the RSA certificate is the check that matters: without it the webhook handler
// refuses every callback, which is how a live payment would silently fail to settle.
$ext = ExtensionHelper::getExtension('gateway', 'Binance', $gateway->settings);
$reflection = new ReflectionClass($ext);
$method = $reflection->getMethod('certificatePublicKey');
$method->setAccessible(true);
$cert = $method->invoke($ext);
step('webhook certificate fetched from Binance', !empty($cert), $cert ? strlen($cert) . ' chars' : 'none — callbacks would all be refused');

// ── 4. A forged callback must be refused ─────────────────────────────────────────────────
$url = route('extensions.gateways.binance.webhook');

$unsigned = Http::timeout(30)->asJson()->post($url, ['bizStatus' => 'PAY_SUCCESS', 'data' => '{}']);
step('unsigned callback refused', $unsigned->status() === 400, 'HTTP ' . $unsigned->status());

$forged = Http::timeout(30)->withHeaders([
    'BinancePay-Timestamp' => (string) (time() * 1000),
    'BinancePay-Nonce' => Str::random(32),
    'BinancePay-Signature' => base64_encode(random_bytes(64)),
])->asJson()->post($url, [
    'bizStatus' => 'PAY_SUCCESS',
    'data' => json_encode(['merchantTradeNo' => $pending?->transaction_id]),
]);
step('forged signature refused', $forged->status() === 400, 'HTTP ' . $forged->status());

$invoice->refresh();
step('invoice untouched by forgeries', $invoice->status !== 'paid', 'status=' . $invoice->status);

// ── Result ───────────────────────────────────────────────────────────────────────────────
$failed = count(array_filter($steps, fn ($s) => !$s));
printf('%s%d of %d checks passed%s', PHP_EOL, count($steps) - $failed, count($steps), PHP_EOL);
echo 'Test user : ' . $user->email . PHP_EOL;
echo 'Invoice   : #' . ($invoice->number ?? $invoice->id) . PHP_EOL;
echo 'Checkout  : ' . ($checkoutUrl ?? '(none)') . PHP_EOL;
echo PHP_EOL . 'Settlement is not simulated: Binance signs callbacks with its own RSA key, so only a' . PHP_EOL;
echo 'real payment produces a valid one. Open the checkout link and pay to confirm the last step.' . PHP_EOL;

exit($failed === 0 ? 0 : 1);
