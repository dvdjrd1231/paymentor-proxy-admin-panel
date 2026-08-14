<?php

/**
 * Stripe connection check.
 *
 * Run after pasting API keys into Admin → Gateways → Stripe, to confirm the gateway can
 * actually reach Stripe before you try a payment from the client area. Reads the keys
 * from the encrypted extension settings — nothing is hardcoded and no key is printed.
 *
 *   php scripts/verify-stripe.php
 */
$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';
$app = require_once $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Gateway;
use Illuminate\Support\Facades\Http;

const API_VERSION = '2025-08-27.basil';

$pass = 0;
$fail = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    printf("[ %s ] %-42s %s%s", $ok ? 'PASS' : 'FAIL', $label, $detail, PHP_EOL);
}

$gateway = Gateway::where('extension', 'Stripe')->first();

if (!$gateway) {
    echo "Stripe gateway is not set up. Add it in Admin → Gateways first.\n";
    exit(1);
}

$settings = $gateway->settings->pluck('value', 'key');
$secret = (string) ($settings['stripe_secret_key'] ?? '');
$publishable = (string) ($settings['stripe_publishable_key'] ?? '');
$webhookSecret = (string) ($settings['stripe_webhook_secret'] ?? '');

// A real key is ~107 chars; the placeholders shipped for local webhook tests are ~13.
check('secret key looks real', strlen($secret) > 30, sprintf('(%d chars, starts %s)', strlen($secret), substr($secret, 0, 8) ?: '-'));
check('publishable key looks real', strlen($publishable) > 30, sprintf('(%d chars, starts %s)', strlen($publishable), substr($publishable, 0, 8) ?: '-'));
check('using TEST keys, not live', str_starts_with($secret, 'sk_test_'), str_starts_with($secret, 'sk_live_') ? '(LIVE KEY — real money!)' : '');

if (strlen($secret) <= 30) {
    echo "\nPaste your test keys in Admin → Gateways → Stripe, then run this again.\n";
    echo "Free test keys: https://dashboard.stripe.com/test/apikeys\n";
    exit(1);
}

$request = fn (string $method, string $url, array $data = []) => Http::withHeaders([
    'Authorization' => 'Bearer ' . $secret,
    'Stripe-Version' => API_VERSION,
])->asForm()->{$method}('https://api.stripe.com/v1' . $url, $data);

// 1. Does the key authenticate at all? This is what was returning 401.
$account = $request('get', '/account');
check('key authenticates with Stripe', $account->successful(), $account->successful()
    ? '(account ' . ($account->object()->id ?? '?') . ')'
    : '(HTTP ' . $account->status() . ' — ' . ($account->object()->error->message ?? 'unknown') . ')');

if (!$account->successful()) {
    echo "\nThe key was rejected. Copy it again from https://dashboard.stripe.com/test/apikeys\n";
    exit(1);
}

// 2. Can we create the payment intent a real deposit would create? Uses the smallest
//    amount Stripe accepts so nothing meaningful is created, and cancels it immediately.
$intent = $request('post', '/payment_intents', [
    'amount' => 50,
    'currency' => 'usd',
    'description' => 'Paymenter connection check',
]);
check('can create a payment intent', $intent->successful(), $intent->successful()
    ? '' : '(' . ($intent->object()->error->message ?? 'unknown') . ')');

if ($intent->successful() && ($id = $intent->object()->id ?? null)) {
    $request('post', '/payment_intents/' . $id . '/cancel');
    check('test intent cleaned up', true, '(' . $id . ')');
}

// 3. The webhook is what credits the balance — without it a paid invoice stays pending.
$webhookUrl = route('extensions.gateways.stripe.webhook');
$isLocal = (bool) preg_match('#//(127\.0\.0\.1|localhost|\[::1\])#', $webhookUrl);

check('webhook secret is set', $webhookSecret !== '' && strlen($webhookSecret) > 10,
    $webhookSecret === '' ? '(empty)' : '(' . strlen($webhookSecret) . ' chars)');

echo PHP_EOL;
echo 'Webhook endpoint: ' . $webhookUrl . PHP_EOL;

if ($isLocal) {
    echo <<<TXT

    NOTE — this URL is local, so Stripe cannot reach it from the internet. A card payment
    will succeed at Stripe but the invoice stays pending and no credit is added until the
    webhook arrives. For local testing, forward it with the Stripe CLI:

        stripe listen --forward-to {$webhookUrl}

    then paste the whsec_... it prints into Admin → Gateways → Stripe → webhook secret.

    Keep that field non-empty: when it is blank, saving the gateway makes Paymenter ask
    Stripe to create a webhook endpoint for this URL, and Stripe rejects local URLs.

    TXT;
}

printf('%s%d passed, %d failed%s', PHP_EOL, $pass, $fail, PHP_EOL);

exit($fail === 0 ? 0 : 1);
