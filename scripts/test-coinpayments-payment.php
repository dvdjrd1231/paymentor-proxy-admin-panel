<?php

/**
 * End-to-end CoinPayments check.
 *
 * Creates a deposit invoice, hands off to CoinPayments exactly as the checkout does, and
 * then waits for CoinPayments' own `invoiceCreated` notification to arrive back — which is
 * what proves the outbound signing, the registered webhook URL, and our inbound signature
 * verification all agree.
 *
 * It cannot complete a payment on its own: that needs coins sent to the checkout. Finish
 * by opening the printed checkout link and paying with LTCT (Litecoin testnet), which is
 * free — the invoice should then settle and the credit appear.
 *
 *   php scripts/test-coinpayments-payment.php [amount]
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

$gateway = Gateway::where('extension', 'CoinPayments')->first();
if (!$gateway) {
    echo "No CoinPayments gateway configured (Admin → Gateways).\n";
    exit(1);
}

$settings = $gateway->settings->pluck('value', 'key');
step('client id configured', !empty($settings['client_id']), (string) ($settings['client_id'] ?? ''));
step('client secret configured', !empty($settings['client_secret']), '<' . strlen((string) ($settings['client_secret'] ?? '')) . ' chars>');

// ── 1. A throwaway customer and a deposit, exactly as the client area creates it ─────────
$user = User::create([
    'first_name' => 'CoinPayments',
    'last_name' => 'E2E',
    'email' => 'cp-e2e-' . Str::random(8) . '@example.test',
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

// ── 2. Hand off to the gateway ───────────────────────────────────────────────────────────
$checkout = null;
try {
    $checkout = ExtensionHelper::pay($gateway, $invoice);
    $ok = is_string($checkout) && str_contains($checkout, 'coinpayments');
    $detail = is_string($checkout) ? $checkout : gettype($checkout);
} catch (Throwable $e) {
    $ok = false;
    $detail = substr($e->getMessage(), 0, 150);
}
step('checkout created at CoinPayments', $ok, $detail);

if (!$ok) {
    echo "\nNothing further to check — fix the credentials or API URL first.\n";
    exit(1);
}

// ── 3. A processing transaction should now be pending against the invoice ────────────────
$pending = $invoice->transactions()->latest('id')->first();
step('pending transaction recorded', $pending !== null, $pending ? $pending->transaction_id : 'none');

// ── 4. CoinPayments sends `invoiceCreated` straight away. Its arrival proves the webhook
//      URL is right and that our signature verification accepts a genuine notification. ──
echo PHP_EOL . 'Waiting for the invoiceCreated notification…' . PHP_EOL;

$logFile = storage_path('logs/laravel-' . date('Y-m-d') . '.log');
$sizeBefore = is_file($logFile) ? filesize($logFile) : 0;
$seen = false;

for ($i = 0; $i < 20; $i++) {
    sleep(3);
    clearstatcache(true, $logFile);
    if (!is_file($logFile)) {
        continue;
    }
    $new = file_get_contents($logFile, false, null, $sizeBefore);
    if (str_contains((string) $new, 'CoinPayments notification received')) {
        $seen = true;
        break;
    }
    if (str_contains((string) $new, 'invalid signature')) {
        step('notification signature accepted', false, 'signature rejected — see the log');
        break;
    }
}

step('notification received and signature accepted', $seen, $seen ? 'after ~' . (($i + 1) * 3) . 's' : 'nothing arrived; check CoinPayments → Webhook History');

// ── 5. Settlement ────────────────────────────────────────────────────────────────────────
//
// Paying for real needs LTCT sent to the checkout, which no script can do. With --settle
// we instead deliver an InvoicePaid notification over real HTTPS to the live endpoint,
// signed with the account's real client secret and shaped exactly like the notifications
// CoinPayments actually sends (captured from live InvoiceCreated deliveries).
//
// That exercises routing, Cloudflare pass-through, signature verification against the real
// credentials, settlement and crediting. The one thing it does not prove is that coins
// moved on-chain — for that, open the checkout link and pay with LTCT.
if (in_array('--settle', $argv, true)) {
    echo PHP_EOL . 'Delivering a signed InvoicePaid to the live endpoint…' . PHP_EOL;

    $settings = $gateway->settings->pluck('value', 'key');
    $clientId = (string) $settings['client_id'];
    $secret = (string) $settings['client_secret'];
    $url = route('extensions.gateways.coinpayments.ipn');

    $cpInvoiceId = $pending?->transaction_id ?? '';

    $deliver = function (string $type) use ($url, $clientId, $secret, $cpInvoiceId, $invoice) {
        $payload = [
            'id' => bin2hex(random_bytes(16)),          // notification id — differs per event
            'type' => $type,
            'timestamp' => gmdate('c'),
            'invoice' => [
                'invoiceId' => (string) $invoice->id,
                'id' => $cpInvoiceId,
                'invoiceNumber' => (string) $invoice->id,
                'lineItems' => [['amount' => (int) round($invoice->total * 100), 'name' => 'Deposit']],
            ],
        ];
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $ts = gmdate('Y-m-d\TH:i:s');
        $sig = base64_encode(hash_hmac('sha256', "\u{feff}" . 'POST' . $url . $clientId . $ts . $body, $secret, true));

        return Illuminate\Support\Facades\Http::withHeaders([
            'X-CoinPayments-Client' => $clientId,
            'X-CoinPayments-Timestamp' => $ts,
            'X-CoinPayments-Signature' => $sig,
            'Content-Type' => 'application/json',
        ])->timeout(30)->withBody($body, 'application/json')->post($url);
    };

    // A forgery must be refused before anything real is accepted.
    $forged = Illuminate\Support\Facades\Http::withHeaders([
        'X-CoinPayments-Client' => $clientId,
        'X-CoinPayments-Timestamp' => gmdate('Y-m-d\TH:i:s'),
        'X-CoinPayments-Signature' => base64_encode(random_bytes(32)),
        'Content-Type' => 'application/json',
    ])->timeout(30)->withBody(json_encode(['type' => 'InvoicePaid']), 'application/json')->post($url);
    step('forged signature refused over HTTPS', $forged->status() === 400, 'HTTP ' . $forged->status());

    $paid = $deliver('InvoicePaid');
    step('signed InvoicePaid accepted over HTTPS', $paid->successful(), 'HTTP ' . $paid->status());

    sleep(2);
    $invoice->refresh();
    step('invoice settled', $invoice->status === 'paid', 'status=' . $invoice->status);

    $rows = fn () => $invoice->transactions()->where('transaction_id', $cpInvoiceId)->count();
    $settled = $invoice->transactions()->where('transaction_id', $cpInvoiceId)->first();
    step('transaction recorded against the invoice', $settled !== null, $settled ? $settled->transaction_id : 'none');

    $credit = $user->credits()->where('currency_code', $invoice->currency_code)->first();
    step('credit balance applied', $credit && (float) $credit->amount === (float) $amount,
        $credit ? $credit->formatted_amount : 'no credit record');

    // CoinPayments sends Completed after Paid, with a different notification id. Keying on
    // that id instead of the invoice id would bank the money twice.
    $before = $rows();
    $deliver('InvoiceCompleted');
    sleep(2);
    step('Paid + Completed do not double-credit', $rows() === $before, 'rows=' . $rows());

    $credit?->refresh();
    step('balance unchanged after the second event', $credit && (float) $credit->amount === (float) $amount,
        $credit ? $credit->formatted_amount : '-');
}

// ── Result ───────────────────────────────────────────────────────────────────────────────
$failed = count(array_filter($steps, fn ($s) => !$s));
printf('%s%d of %d checks passed%s', PHP_EOL, count($steps) - $failed, count($steps), PHP_EOL);
echo 'Test user : ' . $user->email . PHP_EOL;
echo 'Invoice   : #' . ($invoice->number ?? $invoice->id) . PHP_EOL;
echo 'Checkout  : ' . $checkout . PHP_EOL;
echo PHP_EOL . 'To finish the loop, open the checkout link and pay with LTCT (Litecoin testnet, free).' . PHP_EOL;
echo 'The invoice should then flip to paid and the credit appear on the account.' . PHP_EOL;

exit($failed === 0 ? 0 : 1);
