<?php

/**
 * Fill in the branding a fresh install leaves blank.
 *
 * Only settings this platform actually reads are touched here. They are, per
 * app/Classes/Settings.php:
 *
 *   bill_to_text          the seller block on every invoice. Unset, the PDF falls back to
 *                         company_name alone — a seller with a name and no contact details.
 *   system_email_address  where cron failures and update notices go. Marked required in the
 *                         settings definition, yet empty on a fresh install.
 *   tos                   URL to the terms of service. Blank disables the link, which is the
 *                         correct state until a real terms page exists.
 *
 * `company_address`, `company_email`, `terms_url` and `privacy_url` are **not** settings this
 * platform has — an earlier version of this script wrote them and they went nowhere.
 *
 * Values are placeholders built from the platform's own name and URL: complete and
 * consistent, and safe to replace the moment the real details arrive. Nothing is invented
 * about a real company. The address line says it is unconfigured rather than naming a street
 * that does not exist, because a fabricated address on a financial document is worse than a
 * visible gap.
 *
 *   php scripts/set-branding.php            # show what it would change
 *   php scripts/set-branding.php --apply
 *
 * Apply the real details the same way, from the environment:
 *
 *   BILL_TO="Acme Ltda
 *   Rua X 123, São Paulo
 *   CNPJ 00.000.000/0001-00" php scripts/set-branding.php --apply
 */
$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';
$app = require_once $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$apply = in_array('--apply', $argv, true);
echo $apply ? "Applying.\n\n" : "Dry run — nothing will be written. Re-run with --apply.\n\n";

$brand = config('settings.company_name') ?: 'Paymenter';
$url = rtrim((string) (config('settings.app_url') ?: config('app.url')), '/');
$host = parse_url($url, PHP_URL_HOST) ?: 'localhost';

printf("Brand: %s\nURL:   %s\n\n", $brand, $url);

/** Only keys the platform reads, each overridable from the environment. */
$values = [
    'bill_to_text' => getenv('BILL_TO') ?: $brand . "\n" . $url . "\nTrading address not yet configured",
    'system_email_address' => getenv('SYSTEM_EMAIL') ?: 'admin@' . $host,
];

$changed = 0;

foreach ($values as $key => $value) {
    $current = DB::table('settings')->where('key', $key)->whereNull('settingable_type')->value('value');

    if ((string) $current === (string) $value) {
        printf("[ ok  ] %-22s already set\n", $key);
        continue;
    }

    printf("[ %s ] %-22s %s\n", $apply ? ' ok ' : 'todo', $key,
        ($current === null || $current === '') ? '(empty)' : 'replacing existing value');

    foreach (explode("\n", (string) $value) as $line) {
        printf("         %s\n", $line);
    }

    $changed++;

    if (!$apply) {
        continue;
    }

    // updateOrInsert matches a NULL column with `= NULL`, which never matches, so an
    // existing row would be duplicated rather than updated. Decide explicitly.
    $exists = DB::table('settings')->where('key', $key)->whereNull('settingable_type')->exists();

    if ($exists) {
        DB::table('settings')->where('key', $key)->whereNull('settingable_type')
            ->update(['value' => $value, 'updated_at' => now()]);
    } else {
        DB::table('settings')->insert([
            'key' => $key, 'value' => $value, 'type' => 'string', 'encrypted' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

// ── Deliberately left alone ──────────────────────────────────────────────────────────────
echo "\nLeft unset on purpose:\n";

$skip = [
    'tos' => 'a URL to real terms. Blank disables the link; pointing it at a page that does '
        . 'not exist would show customers a 404 at checkout',
    'logo' => 'an image file — upload in Admin → Settings. A setting pointing at a missing '
        . 'file gives a broken image on every invoice, which is worse than the text fallback',
    'logo_dark' => 'as above, for dark backgrounds',
    'favicon' => 'an image file — upload in Admin → Settings',
];

foreach ($skip as $key => $why) {
    $value = DB::table('settings')->where('key', $key)->whereNull('settingable_type')->value('value');
    printf("  %-20s %s\n", $key, ($value === null || $value === '') ? $why : 'already set: ' . $value);
}

printf("\n%d value(s) %s.\n", $changed, $apply ? 'written' : 'would change');

if ($apply) {
    echo "\nSettings are cached. Run:\n";
    echo "  php artisan cache:clear && php artisan config:clear\n";
    echo "then restart the container, or the old values keep showing.\n";
} else {
    echo "\nNothing was written. Re-run with --apply.\n";
}
