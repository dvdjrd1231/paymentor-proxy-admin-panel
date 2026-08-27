<?php

/**
 * CAPTCHA on the admin sign-in page — the server-side half.
 *
 * The widget itself is a browser thing and is checked in a browser; what this proves is the
 * part that actually decides whether a sign-in is allowed:
 *
 *   1. The gate. It is enforced when a provider and both keys are set, and — deliberately —
 *      not when a key is missing, because `Captchable` fails any submission without a token
 *      and no site key can produce one. That combination would leave an unsatisfiable form
 *      on the one page you need in order to fix it.
 *   2. The check. A missing token is refused, and a token the provider rejects is refused.
 *   3. The form. The challenge is in the sign-in schema when enforced, and absent when not.
 *   4. The view renders, carrying the configured site key.
 *
 * Step 2's second half makes a real call to the configured provider with a junk token, so it
 * needs network access and the store's own keys — that is the point, it is verifying that
 * this install's secret is one the provider will answer for.
 *
 * Changes no data and no settings: every permutation is set with `config()`, which lasts as
 * long as this process.
 *
 *   php scripts/test-admin-captcha.php
 */
// Imports sit above the bootstrap, not below it, and that is load-bearing: PHP resolves
// aliases in parse order, so a `use` declared after this block does not apply to it and
// `Kernel::class` there resolves to a non-existent global `Kernel`. Pint keeps the block
// wherever it finds it and shortens any fully-qualified name back to the alias, so above
// is the only arrangement that survives both. Found by running the script.
use Filament\Schemas\Schema;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Paymenter\Extensions\Others\AdminOps\Admin\Auth\Login;

$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';
$app = require_once $base . '/bootstrap/app.php';
// Fully qualified, not the imported alias: Pint orders `use` statements after this
// bootstrap block, and PHP resolves aliases in parse order — an alias declared below
// this line does not apply to it, so `Kernel::class` would resolve to a global `Kernel`
// that does not exist. Found by running it.
$app->make(Kernel::class)->bootstrap();

$steps = [];

function step(string $label, bool $ok, string $detail = ''): bool
{
    global $steps;
    $steps[] = $ok;
    printf('[ %s ] %-52s %s%s', $ok ? 'PASS' : 'FAIL', $label, $detail, PHP_EOL);

    return $ok;
}

/** Calls a private method on the page — the gate is not part of its public surface. */
function enforced(Login $login): bool
{
    $method = new ReflectionMethod($login, 'captchaEnforced');
    $method->setAccessible(true);

    return $method->invoke($login);
}

function verify(Login $login, string $token): ?string
{
    $login->captcha = $token;

    $method = new ReflectionMethod($login, 'captcha');
    $method->setAccessible(true);

    try {
        $method->invoke($login);
    } catch (ValidationException $exception) {
        return $exception->validator->errors()->first('captcha');
    }

    return null;
}

// The extension registers this in boot(); stated again so the script works even if AdminOps
// is disabled at the time — the point is to test the code, not the toggle.
View::addNamespace('adminops', $base . '/extensions/Others/AdminOps/resources/views');

$configured = [
    'provider' => config('settings.captcha'),
    'siteKey' => config('settings.captcha_site_key'),
    'secret' => config('settings.captcha_secret'),
];

$login = new Login;

// ── 1. The gate ──────────────────────────────────────────────────────────────────────────
$permutations = [
    ['off', ['captcha' => 'disabled', 'captcha_site_key' => 'k', 'captcha_secret' => 's'], false],
    ['unset', ['captcha' => null, 'captcha_site_key' => 'k', 'captcha_secret' => 's'], false],
    ['no site key', ['captcha' => 'recaptcha-v2', 'captcha_site_key' => '', 'captcha_secret' => 's'], false],
    ['no secret', ['captcha' => 'recaptcha-v2', 'captcha_site_key' => 'k', 'captcha_secret' => ''], false],
    ['fully configured', ['captcha' => 'recaptcha-v2', 'captcha_site_key' => 'k', 'captcha_secret' => 's'], true],
];

foreach ($permutations as [$label, $settings, $expected]) {
    config(array_combine(
        array_map(fn (string $key): string => 'settings.' . $key, array_keys($settings)),
        $settings,
    ));

    step('gate — ' . $label, enforced($login) === $expected, $expected ? 'enforced' : 'open');
}

// ── 2. The check ─────────────────────────────────────────────────────────────────────────
config([
    'settings.captcha' => $configured['provider'] ?: 'recaptcha-v2',
    'settings.captcha_site_key' => $configured['siteKey'],
    'settings.captcha_secret' => $configured['secret'],
]);

step('empty token refused', verify($login, '') === 'The CAPTCHA is required.');

if (filled($configured['secret'])) {
    $error = verify($login, 'not-a-real-token');
    step('junk token refused by ' . config('settings.captcha'), $error === 'The CAPTCHA was invalid.',
        $error ?? 'accepted — the provider answered success for a junk token');
} else {
    step('junk token refused', false, 'skipped: no secret configured on this install');
    array_pop($steps);
}

// ── 3. The form ──────────────────────────────────────────────────────────────────────────
$componentCount = function (Login $login): int {
    $schema = Schema::make($login);

    return count($login->form($schema)->getComponents());
};

config(['settings.captcha' => 'recaptcha-v2', 'settings.captcha_site_key' => 'k', 'settings.captcha_secret' => 's']);
$with = $componentCount($login);

config(['settings.captcha' => 'disabled']);
$without = $componentCount($login);

step('in the sign-in form when enforced', $with === 4, $with . ' components');
step('absent when not', $without === 3, $without . ' components');

// ── 4. The view ──────────────────────────────────────────────────────────────────────────
config([
    'settings.captcha' => 'recaptcha-v2',
    'settings.captcha_site_key' => 'site-key-under-test',
    'settings.captcha_secret' => 's',
]);

$html = view('adminops::captcha', ['errors' => new ViewErrorBag])->render();

step('view renders', str_contains($html, 'ao-captcha-widget'), strlen($html) . ' bytes');
step('carries the site key', str_contains($html, 'site-key-under-test'));
step('widget is wire:ignore', str_contains($html, 'wire:ignore'));

// ─────────────────────────────────────────────────────────────────────────────────────────
$passed = count(array_filter($steps));
printf('%s%d/%d passed%s', PHP_EOL, $passed, count($steps), PHP_EOL);

exit($passed === count($steps) ? 0 : 1);
