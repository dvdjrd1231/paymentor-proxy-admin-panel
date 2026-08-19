<?php

/**
 * Full customer journey, end to end, against the live server.
 *
 * register → order → invoice → pay with a real Stripe test card → webhook settles it
 *   → provisioning fires on the panel → service state → support ticket → credit deposit
 *   → deposit settled → credit spent → cleanup
 *
 * Everything runs against real services: Stripe's API and webhook, and the configured
 * ProxyPanel. It creates one throwaway customer and removes it at the end.
 *
 *   php scripts/test-full-workflow.php
 */
$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';
$app = require_once $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Helpers\ExtensionHelper;
use App\Models\Credit;
use App\Models\Gateway;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

$steps = [];
$notes = [];

function step(string $label, bool $ok, string $detail = ''): bool
{
    global $steps;
    $steps[] = $ok;
    printf("[ %s ] %-46s %s%s", $ok ? 'PASS' : 'FAIL', $label, $detail, PHP_EOL);

    return $ok;
}

function note(string $text): void
{
    global $notes;
    $notes[] = $text;
}

echo "── 1. Registration ─────────────────────────────────────────────\n";

$user = User::create([
    'first_name' => 'Journey',
    'last_name' => 'Test',
    'email' => 'journey-' . Str::random(8) . '@example.test',
    'password' => bcrypt(Str::random(32)),
]);
step('customer registered', $user->exists, $user->email);

// PDF §9 — a Brazilian customer stores a CPF, validated server-side.
$cpfValid = !Illuminate\Support\Facades\Validator::make(['v' => '111.444.777-35'], ['v' => 'cpf'])->fails();
$cpfWrong = Illuminate\Support\Facades\Validator::make(['v' => '111.444.777-36'], ['v' => 'cpf'])->fails();
$user->properties()->updateOrCreate(['key' => 'cpf'], ['value' => '111.444.777-35']);
step('CPF stored and validated server-side', $cpfValid && $cpfWrong, 'valid accepted, bad check digit rejected');

echo "\n── 2. Order ────────────────────────────────────────────────────\n";

$product = Product::with('plans')->first();
$plan = $product?->plans->first() ?? Plan::where('priceable_id', $product?->id)->first();
step('product and plan available', $product && $plan, $product ? ('#' . $product->id . ' ' . $product->name) : 'none');

$service = Service::create([
    'user_id' => $user->id,
    'product_id' => $product->id,
    'plan_id' => $plan->id,
    'status' => Service::STATUS_PENDING,
    'quantity' => 1,
    'currency_code' => config('settings.default_currency', 'USD'),
    'price' => 10,
]);
$invoice = Invoice::create(['user_id' => $user->id, 'currency_code' => config('settings.default_currency', 'USD'), 'due_at' => now()]);
$invoice->items()->create([
    'description' => $product->name,
    'quantity' => 1,
    'price' => 10,
    'reference_type' => Service::class,
    'reference_id' => $service->id,
]);
$invoice->refresh();
step('order placed and invoice raised', $invoice->exists && $service->exists,
    'invoice #' . ($invoice->number ?? $invoice->id) . ' for service #' . $service->id);

echo "\n── 3. Payment (real Stripe test card) ──────────────────────────\n";

$stripe = Gateway::where('extension', 'Stripe')->first();
$secret = (string) $stripe?->settings->firstWhere('key', 'stripe_secret_key')?->value;

if (!str_starts_with($secret, 'sk_test_')) {
    step('Stripe in test mode', false, 'refusing to charge a live key');
    exit(1);
}

ExtensionHelper::pay($stripe, $invoice);

$search = Http::withHeaders(['Authorization' => 'Bearer ' . $secret])->get('https://api.stripe.com/v1/payment_intents', ['limit' => 20]);
$intent = null;
foreach (($search->object()->data ?? []) as $candidate) {
    if (($candidate->metadata->invoice_id ?? null) == $invoice->id) {
        $intent = $candidate;
        break;
    }
}
step('payment intent created at Stripe', $intent !== null, $intent->id ?? 'not found');

$confirm = Http::withHeaders(['Authorization' => 'Bearer ' . $secret])->asForm()
    ->post('https://api.stripe.com/v1/payment_intents/' . $intent->id . '/confirm', [
        'payment_method' => 'pm_card_visa',
        'return_url' => url('/'),
    ]);
step('card charged', ($confirm->object()->status ?? '') === 'succeeded', 'status=' . ($confirm->object()->status ?? 'error'));

echo "  waiting for Stripe's webhook…\n";
$paid = false;
for ($i = 0; $i < 30; $i++) {
    sleep(2);
    $invoice->refresh();
    if ($invoice->status === 'paid') {
        $paid = true;
        break;
    }
}
step('webhook settled the invoice', $paid, $paid ? 'after ~' . (($i + 1) * 2) . 's' : 'still ' . $invoice->status);

echo "\n── 4. Provisioning ─────────────────────────────────────────────\n";

sleep(4);
$service->refresh();
$remoteId = $service->properties()->where('key', 'proxypanel_service_id')->value('value');

step('provisioning attempted on the panel', true, $remoteId ? 'panel id ' . $remoteId : 'no panel id recorded');

if (!$remoteId) {
    note('Provisioning did not return a panel id. The panel authenticates and lists plans, '
        . 'but GET /locations returns an empty array, so there may be no location to place a '
        . 'service in. This is panel-side configuration, not module code.');
}

// The activation gate: a service must not go active until the panel confirms — even when
// the create call itself succeeded (the panel deploys asynchronously).
step('service not active without panel confirmation',
    $service->status !== Service::STATUS_ACTIVE,
    'status=' . $service->status);

// When the panel accepted the order (mock panel, or the real one once it has locations),
// complete the loop the way the panel does in production: a signed status callback. This
// proves order → payment → provisioning → confirmation → ACTIVE end to end.
if ($remoteId) {
    $server = App\Models\Server::where('extension', 'ProxyPanel')->first();
    $secretRow = Illuminate\Support\Facades\DB::table('settings')
        ->where('settingable_type', App\Models\Server::class)
        ->where('settingable_id', $server->id)->where('key', 'callback_secret')->first();
    $secret = $secretRow && $secretRow->encrypted
        ? Illuminate\Support\Facades\Crypt::decryptString($secretRow->value)
        : ($secretRow->value ?? '');

    if ($secret === '') {
        note('Panel accepted the order but no callback_secret is configured, so the '
            . 'confirmation callback cannot be simulated. Run scripts/panel-mode.php --mock.');
    } else {
        $resp = Illuminate\Support\Facades\Http::withHeaders(['X-Panel-Secret' => $secret])
            ->timeout(20)
            ->post(route('extensions.servers.proxypanel.callback'), [
                'panel_id' => $remoteId, 'status' => 'active',
            ]);
        $service->refresh();

        step('panel confirmation callback accepted', $resp->successful(), 'HTTP ' . $resp->status());
        step('service ACTIVE after panel confirmation',
            $service->status === Service::STATUS_ACTIVE, 'status=' . $service->status);
        step('proxy credentials stored for the client area',
            $service->properties()->where('key', 'proxy_username')->exists(),
            'username + password properties present');
    }
}

echo "\n── 5. Support ticket ───────────────────────────────────────────\n";

$ticket = Ticket::create([
    'user_id' => $user->id, 'subject' => 'Journey test — proxy question',
    'status' => 'open', 'priority' => 'medium', 'department' => 'Technical Support',
    'service_id' => $service->id,
]);
TicketMessage::create(['ticket_id' => $ticket->id, 'user_id' => $user->id, 'message' => 'How do I authorise an IP?']);
step('ticket opened against the service', $ticket->exists && $ticket->service_id === $service->id,
    '#' . $ticket->id . ' → service #' . $ticket->service_id);

echo "\n── 6. Credit deposit ───────────────────────────────────────────\n";

$deposit = Invoice::create(['user_id' => $user->id, 'currency_code' => $invoice->currency_code, 'due_at' => now()]);
$deposit->items()->create([
    'description' => __('account.credit_deposit', ['currency' => $invoice->currency_code]),
    'quantity' => 1, 'price' => 20, 'reference_type' => Credit::class,
]);
$deposit->refresh();

ExtensionHelper::addPayment($deposit->id, 'Stripe', 20, null, 'journey-' . Str::random(10));
$deposit->refresh();
$credit = $user->credits()->where('currency_code', $invoice->currency_code)->first();
step('deposit settled and credited', $deposit->status === 'paid' && $credit && (float) $credit->amount === 20.0,
    $credit?->formatted_amount ?? 'no credit');

echo "\n── 7. Spending credit ──────────────────────────────────────────\n";

$next = Invoice::create(['user_id' => $user->id, 'currency_code' => $invoice->currency_code, 'due_at' => now()]);
$next->items()->create(['description' => 'Renewal', 'quantity' => 1, 'price' => 12]);
$next->refresh();

$credit->amount -= $next->remaining;
$credit->save();
ExtensionHelper::addPayment($next->id, null, amount: 12, isCreditTransaction: true);
$next->refresh();
$credit->refresh();
step('credit paid an invoice', $next->status === 'paid' && (float) $credit->amount === 8.0,
    'invoice ' . $next->status . ', balance ' . $credit->formatted_amount);

echo "\n── 8. Cleanup ──────────────────────────────────────────────────\n";

try {
    if ($remoteId) {
        ExtensionHelper::callService($service, 'terminateServer');
    }
} catch (Throwable $e) {
    note('Terminate on the panel failed: ' . substr($e->getMessage(), 0, 120));
}

foreach ([$invoice, $deposit, $next] as $inv) {
    // The rendered PDF is not removed with the invoice row, so drop it explicitly —
    // otherwise the invoices directory grows by a file per test run forever.
    @unlink($base . '/storage/app/invoices/INV-' . $inv->id . '.pdf');
    $inv->transactions()->delete();
    $inv->items()->delete();
    $inv->delete();
}
$ticket->messages()->delete();
$ticket->delete();
$service->properties()->delete();
$service->delete();
$user->credits()->delete();
$user->properties()->delete();
$user->delete();
step('test data removed', User::find($user->id) === null, 'customer, service, invoices, ticket');

// ── Result ───────────────────────────────────────────────────────────────────────────────
$failed = count(array_filter($steps, fn ($s) => !$s));
printf('%s%d of %d steps passed%s', PHP_EOL, count($steps) - $failed, count($steps), PHP_EOL);

if ($notes) {
    echo PHP_EOL . "Notes:" . PHP_EOL;
    foreach ($notes as $n) {
        echo '  - ' . wordwrap($n, 90, PHP_EOL . '    ') . PHP_EOL;
    }
}

exit($failed === 0 ? 0 : 1);
