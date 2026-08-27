<?php

/**
 * Fixed-term products — the sixth requirement, checked end to end.
 *
 * *"Daily/Weekly: must be completed within the set timeframe (usage hours equivalent to the
 * contracted period); non-renewable; time extensions possible by the administrator or support
 * based on specific, justifiable needs regarding maintenance or downtime. Monthly products are
 * automatically renewable."*
 *
 * Every clause of that is a check here:
 *
 *   - a daily plan opens a 24-hour term, a weekly one 168 — counted from activation, not from
 *     the order, because an order that waited a day for provisioning has used none of it;
 *   - a monthly plan opens **no** term at all, so nothing here can ever stop it renewing;
 *   - the clock alone ends it — no invoice is raised, which is what non-renewable means;
 *   - an extension adds to `ends_at` rather than to `now`, needs a reason, and is recorded
 *     against the admin who granted it;
 *   - an extension granted *after* the term lapsed reopens it, since that is the usual case:
 *     the customer notices when the proxy stops, not before;
 *   - a product-level override beats whatever the plan says.
 *
 * Nothing is left behind: every row this creates is removed in a `finally`, including on
 * failure, and no real service is touched — the fixtures are `@example.test` throughout.
 *
 * `--sweep` additionally lets the real sweeper act on the expired fixture, proving the cron
 * path rather than only the support class. That path calls the provisioning module, so it is
 * opt-in.
 *
 *   php scripts/test-term-limits.php [--sweep]
 */

use App\Models\Plan;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;
use Paymenter\Extensions\Others\TermLimits\Models\ProductTerm;
use Paymenter\Extensions\Others\TermLimits\Models\ServiceTerm;
use Paymenter\Extensions\Others\TermLimits\Support\Sweeper;
use Paymenter\Extensions\Others\TermLimits\Support\Terms;

// Above the bootstrap deliberately: PHP resolves aliases in parse order, so a `use` below it
// would not apply to `Kernel::class` here. See the note in test-catalogue-order.php.
$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';
$app = require_once $base . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$sweep = in_array('--sweep', $argv, true);
$steps = [];

function step(string $label, bool $ok, string $detail = ''): bool
{
    global $steps;
    $steps[] = $ok;
    printf('[ %s ] %-52s %s%s', $ok ? 'PASS' : 'FAIL', $label, $detail, PHP_EOL);

    return $ok;
}

$made = ['services' => [], 'plans' => [], 'products' => [], 'users' => [], 'terms' => []];

/** A service on a plan of the given shape, active as of now. */
function fixture(string $type, ?string $unit, ?int $period, array &$made, User $user): Service
{
    $product = Product::create([
        'name' => 'term-test ' . Str::random(6),
        'slug' => 'term-test-' . Str::lower(Str::random(10)),
        'category_id' => Product::whereNotNull('category_id')->value('category_id'),
    ]);
    $made['products'][] = $product->id;

    // Through the morph relation — `priceable_type`/`priceable_id` are not fillable, which
    // is how core itself creates a plan for a product.
    $plan = $product->plans()->create([
        'name' => $type . ' fixture',
        'type' => $type,
        'billing_period' => $period,
        'billing_unit' => $unit,
    ]);
    $made['plans'][] = $plan->id;

    $service = Service::create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'quantity' => 1,
        'price' => 1,
        'currency_code' => 'USD',
    ]);
    $made['services'][] = $service->id;

    return $service->fresh(['plan', 'product']);
}

try {
    $user = User::create([
        'first_name' => 'Term',
        'last_name' => 'Fixture',
        'email' => 'term-e2e-' . Str::random(8) . '@example.test',
        'password' => bcrypt(Str::random(32)),
    ]);
    $made['users'][] = $user->id;

    // ── The contracted length ────────────────────────────────────────────────────────────
    $daily = fixture('one-time', 'day', 1, $made, $user);
    $weekly = fixture('one-time', 'week', 1, $made, $user);
    $monthly = fixture('recurring', 'month', 1, $made, $user);

    step('daily plan is a 24-hour term', Terms::length($daily) === 24, (string) Terms::length($daily) . 'h');
    step('weekly plan is a 168-hour term', Terms::length($weekly) === 168, (string) Terms::length($weekly) . 'h');
    step('monthly plan has no term (auto-renews)', Terms::length($monthly) === null, var_export(Terms::length($monthly), true));

    // ── Opening the clock ────────────────────────────────────────────────────────────────
    $term = Terms::open($daily);
    $made['terms'][] = $term?->id;

    step('term opens on activation', $term instanceof ServiceTerm && $term->isOpen());
    step('runs from now, not the order date',
        $term && abs($term->started_at->diffInSeconds(now())) < 5);
    // Carbon 3 returns a float from diffInHours, so compare by value, not by type.
    step('ends exactly one contracted period later',
        $term && (int) round(abs($term->ends_at->diffInHours($term->started_at))) === 24);

    // Opening twice must not restart the clock a customer already paid for.
    $again = Terms::open($daily);
    step('re-provisioning does not restart the clock', $again && $again->id === $term->id);

    step('no term is opened for a monthly service', Terms::open($monthly) === null);

    // ── Non-renewable ────────────────────────────────────────────────────────────────────
    $invoicesBefore = $daily->invoiceItems()->count();

    // ── Not due until it is ──────────────────────────────────────────────────────────────
    step('not due while time remains', !Terms::due()->contains('id', $term->id));

    // Wind the clock back rather than waiting a day.
    $term->update(['ends_at' => now()->subHour()]);
    step('due once the time is up', Terms::due()->contains('id', $term->id));

    // ── Extending, with a reason ─────────────────────────────────────────────────────────
    $endsAt = $term->fresh()->ends_at->copy();
    $extension = Terms::extend($term, 6, 'Panel maintenance 03:00-09:00', $user);
    $term->refresh();

    step('extension adds to ends_at, not to now',
        (int) round(abs($term->ends_at->diffInHours($endsAt))) === 6, '+6h from the old end');
    step('extension records the reason', $extension->reason === 'Panel maintenance 03:00-09:00');
    step('extension records who granted it', (int) $extension->admin_id === (int) $user->id);
    step('an extended term is no longer due', !Terms::due()->contains('id', $term->id));

    // ── Extending a term that already lapsed ─────────────────────────────────────────────
    $term->update(['ends_at' => now()->subHours(2), 'ended_at' => now()->subHour(), 'outcome' => ServiceTerm::OUTCOME_TERMINATED]);
    step('a lapsed term is closed', !$term->fresh()->isOpen());

    Terms::extend($term, 24, 'Customer reported downtime', $user);
    $term->refresh();

    step('extending a lapsed term reopens it', $term->isOpen());
    step('and it is not due again', !Terms::due()->contains('id', $term->id));

    // ── Product override beats the plan ──────────────────────────────────────────────────
    ProductTerm::updateOrCreate(['product_id' => $monthly->product_id], ['days' => 3]);
    step('product override beats the plan (3-day trial on a monthly plan)',
        Terms::length($monthly->fresh(['plan'])) === 72, (string) Terms::length($monthly->fresh(['plan'])) . 'h');

    ProductTerm::where('product_id', $monthly->product_id)->delete();
    step('removing the override restores auto-renew', Terms::length($monthly->fresh(['plan'])) === null);

    // ── Nothing was billed for any of it ─────────────────────────────────────────────────
    step('non-renewable: no invoice raised by any of this',
        $daily->invoiceItems()->count() === $invoicesBefore, 'items=' . $daily->invoiceItems()->count());

    // ── The cron path ────────────────────────────────────────────────────────────────────
    $term->update(['ends_at' => now()->subHour()]);

    $dry = Sweeper::run(dryRun: true);
    step('sweeper sees it in a dry run', $dry['stopped'] >= 1, 'stopped=' . $dry['stopped']);
    step('dry run changed nothing', $term->fresh()->isOpen());

    if ($sweep) {
        Sweeper::run();
        $term->refresh();
        step('sweeper closes the term', !$term->isOpen(), 'outcome=' . ($term->outcome ?? '-'));
        step('service is no longer active', $daily->fresh()->status !== 'active', 'status=' . $daily->fresh()->status);
    } else {
        echo PHP_EOL, '  (--sweep not given: the live termination path was not run.)', PHP_EOL, PHP_EOL;
    }
} finally {
    // Remove everything, whatever happened above.
    foreach ($made['terms'] as $id) {
        if ($id) {
            ServiceTerm::whereKey($id)->each(fn (ServiceTerm $t) => $t->extensions()->delete());
            ServiceTerm::whereKey($id)->delete();
        }
    }

    ServiceTerm::whereIn('service_id', $made['services'])->each(function (ServiceTerm $t): void {
        $t->extensions()->delete();
        $t->delete();
    });

    ProductTerm::whereIn('product_id', $made['products'])->delete();
    Service::whereIn('id', $made['services'])->forceDelete();
    Plan::whereIn('id', $made['plans'])->delete();
    Product::whereIn('id', $made['products'])->forceDelete();
    User::whereIn('id', $made['users'])->forceDelete();

    printf(
        '%sremoved %d service(s), %d plan(s), %d product(s), %d user(s)%s',
        PHP_EOL, count($made['services']), count($made['plans']),
        count($made['products']), count($made['users']), PHP_EOL,
    );
}

$passed = count(array_filter($steps));
printf('%d/%d passed%s', $passed, count($steps), PHP_EOL);

exit($passed === count($steps) ? 0 : 1);
