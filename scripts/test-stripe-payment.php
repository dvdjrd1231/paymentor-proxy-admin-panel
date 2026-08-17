<?php

/**
 * End-to-end Stripe payment test.
 *
 * Drives a real payment through Stripe in TEST mode and waits for Stripe's own webhook to
 * come back and settle the invoice. Unlike recording a payment by hand, this exercises the
 * two links nothing else covers: the outbound call to Stripe, and the inbound webhook.
 *
 * It creates a throwaway customer and a credit-deposit invoice, so run it on a staging
 * deployment. It refuses to run against live keys.
 *
 *   php scripts/test-stripe-payment.php [amount]
 *
 * The webhook must be able to reach this site. On localhost, run first:
 *   stripe listen --forward-to http://127.0.0.1:8080/extensions/stripe/webhook
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

const API_VERSION = '2025-08-27.basil';

$amount = (float) ($argv[1] ?? 25);
$steps = [];

function step(string $label, bool $ok, string $detail = ''): bool
{
    global $steps;
    $steps[] = $ok;
    printf("[ %s ] %-44s %s%s", $ok ? 'PASS' : 'FAIL', $label, $detail, PHP_EOL);

    return $ok;
}

$gateway = Gateway::where('extension', 'Stripe')->first();
if (!$gateway) {
    echo "No Stripe gateway configured.\n";
    exit(1);
}

$secret = (string) $gateway->settings->firstWhere('key', 'stripe_secret_key')?->value;

if (!str_starts_with($secret, 'sk_test_')) {
    echo "Refusing to run: the Stripe key is not a test key. This script makes real charges otherwise.\n";
    exit(1);
}

$stripe = fn (string $method, string $url, array $data = []) => Http::withHeaders([
    'Authorization' => 'Bearer ' . $secret,
    'Stripe-Version' => API_VERSION,
])->asForm()->{$method}('https://api.stripe.com/v1' . $url, $data);

// ── 1. A throwaway customer and a credit deposit, exactly as the client area creates it ──
$user = User::create([
    'first_name' => 'Stripe',
    'last_name' => 'E2E',
    'email' => 'stripe-e2e-' . Str::random(8) . '@example.test',
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

// ── 2. Hand off to the gateway exactly as the checkout does ──────────────────────────────
try {
    ExtensionHelper::pay($gateway, $invoice);
    $handoff = true;
    $detail = '';
} catch (Throwable $e) {
    $handoff = false;
    $detail = substr($e->getMessage(), 0, 120);
}
step('gateway hand-off (payment intent created)', $handoff, $detail);

if (!$handoff) {
    echo "\nStripe rejected the hand-off, so there is nothing to confirm. Fix the keys first:\n";
    echo "  php scripts/verify-stripe.php\n";
    exit(1);
}

// Find the intent Paymenter just created for this invoice.
$search = $stripe('get', '/payment_intents', ['limit' => 20]);
$intent = null;
foreach (($search->object()->data ?? []) as $candidate) {
    if (($candidate->metadata->invoice_id ?? null) == $invoice->id) {
        $intent = $candidate;
        break;
    }
}

if (!step('intent carries invoice_id metadata', $intent !== null, $intent ? $intent->id : 'not found — the webhook could not map it back')) {
    exit(1);
}

// ── 3. Pay it with a test card. This is what a customer's browser would do. ──────────────
$confirm = $stripe('post', '/payment_intents/' . $intent->id . '/confirm', [
    'payment_method' => 'pm_card_visa',
    'return_url' => url('/'),
]);
$status = $confirm->object()->status ?? 'error';
step('card payment confirmed at Stripe', $status === 'succeeded', $confirm->successful()
    ? 'status=' . $status
    : ($confirm->object()->error->message ?? 'unknown'));

// ── 4. Stripe now calls our webhook. That is the link nothing else tests. ────────────────
echo PHP_EOL . 'Waiting for Stripe to deliver payment_intent.succeeded…' . PHP_EOL;

$paid = false;
for ($i = 0; $i < 30; $i++) {
    sleep(2);
    $invoice->refresh();
    if ($invoice->status === 'paid') {
        $paid = true;
        break;
    }
}

step('webhook arrived and marked the invoice paid', $paid, $paid
    ? 'after ~' . (($i + 1) * 2) . 's'
    : 'still ' . $invoice->status . ' — check Stripe Dashboard → Developers → Webhooks');

$transaction = $invoice->transactions()->latest('id')->first();
step('transaction recorded against the invoice', $transaction !== null, $transaction
    ? $transaction->transaction_id . ' (' . $transaction->amount . ')'
    : 'none');

$credit = $user->credits()->where('currency_code', $invoice->currency_code)->first();
step('credit balance applied', $credit && (float) $credit->amount === $amount, $credit
    ? $credit->formatted_amount
    : 'no credit record');

// ── Result ───────────────────────────────────────────────────────────────────────────────
$failed = count(array_filter($steps, fn ($s) => !$s));
printf('%s%d of %d steps passed%s', PHP_EOL, count($steps) - $failed, count($steps), PHP_EOL);
echo 'Test user: ' . $user->email . ' (id ' . $user->id . '), invoice #' . ($invoice->number ?? $invoice->id) . PHP_EOL;

if ($failed === 0) {
    // Remove the throwaway account on success only — a failed run keeps its data so the
    // invoice, transactions and credit can be inspected. `scripts/cleanup-test-data.php`
    // clears whatever is left once the diagnosis is done.
    $invoice->transactions()->delete();
    $invoice->items()->delete();
    $invoice->delete();
    $user->credits()->delete();
    $user->properties()->delete();
    $user->delete();
    echo 'Test data removed.' . PHP_EOL;

    echo PHP_EOL . 'Stripe works end to end: checkout → Stripe → webhook → paid → credit applied.' . PHP_EOL;
}

exit($failed === 0 ? 0 : 1);
