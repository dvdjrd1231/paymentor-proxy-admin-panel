<?php

/**
 * Replace Paymenter's own name with the client's throughout the platform.
 *
 * A fresh install ships `company_name = "Paymenter"`, and the invoice template falls back to
 * that for the seller block (`bill_to_text ?: company_name`), so every invoice a customer
 * receives is issued by "Paymenter" rather than by the business. The same name goes out as
 * the sender on notification mail.
 *
 * The brand here is taken from the client's own live store, my.noxproxy.com, which is the
 * same source the catalogue prices came from — not invented.
 *
 * What this deliberately does NOT set, because guessing them would be worse than leaving
 * them empty, and each has to come from the client:
 *
 *   company_address   the registered trading address printed on invoices
 *   company_email     a support address on the real domain, once it exists
 *   logo / favicon    image files, uploaded through Admin → Settings
 *   terms_url         links a customer is legally held to
 *   privacy_url
 *
 * Override the brand with BRAND= if it is ever wrong:
 *
 *   php scripts/set-branding.php                 # show what it would change
 *   php scripts/set-branding.php --apply
 *   BRAND="Some Other Name" php scripts/set-branding.php --apply
 */
$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';
$app = require_once $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$apply = in_array('--apply', $argv, true);
$brand = getenv('BRAND') ?: 'NoxProxy';

echo $apply ? "Applying.\n\n" : "Dry run — nothing will be written. Re-run with --apply.\n\n";
echo "Brand: {$brand}\n\n";

/** Settings that carry the platform's name in front of a customer. */
$branding = [
    'app_name' => $brand,
    'company_name' => $brand,
    'mail_from_name' => $brand,
];

foreach ($branding as $key => $value) {
    $current = DB::table('settings')->where('key', $key)->whereNull('settingable_type')->value('value');

    if ($current === $value) {
        printf("[ ok  ] %-16s already %s\n", $key, $value);
        continue;
    }

    printf("[ %s ] %-16s %s -> %s\n", $apply ? ' ok ' : 'todo', $key,
        ($current === null || $current === '') ? '(not set)' : $current, $value);

    if ($apply) {
        DB::table('settings')->updateOrInsert(
            ['key' => $key, 'settingable_type' => null, 'settingable_id' => null],
            ['value' => $value, 'type' => 'string', 'encrypted' => 0, 'updated_at' => now(), 'created_at' => now()],
        );
    }
}

// ── What only the client can supply ──────────────────────────────────────────────────────
$missing = [
    'company_address' => 'printed on every invoice as the seller address',
    'company_email' => 'the support address customers reply to',
    'logo' => 'shown on invoices and in the client area',
    'favicon' => 'the browser tab icon',
    'terms_url' => 'linked at checkout; customers are held to it',
    'privacy_url' => 'linked at checkout',
];

echo "\nStill needed from the client:\n";
$outstanding = 0;

foreach ($missing as $key => $why) {
    $value = DB::table('settings')->where('key', $key)->whereNull('settingable_type')->value('value');

    if ($value === null || $value === '') {
        $outstanding++;
        printf("  %-16s not set — %s\n", $key, $why);
    } else {
        printf("  %-16s set\n", $key);
    }
}

printf("\n%d branding value(s) still outstanding.\n", $outstanding);

if ($apply) {
    echo "\nRun `php artisan config:clear` so the new values are picked up.\n";
} else {
    echo "\nNothing was written. Re-run with --apply.\n";
}
