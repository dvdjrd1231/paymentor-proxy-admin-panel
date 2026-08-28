<?php

/**
 * Bring existing clients up to the Add New Client form's standard.
 *
 * The form (AdminOps → Add New Client) stores the reference's Email Notifications and
 * Settings blocks as properties on every client it creates — six `email_pref_*` rows and
 * eight `setting_*` rows, at WHMCS's own defaults. Clients created before the form existed
 * have none of them, so their profiles are missing rows the UI now treats as standard.
 *
 * This inserts the missing rows and touches nothing else: a preference a client already
 * carries — whatever its value — is left exactly as it is, `admin_notes` is never created
 * here, and administrators (role_id set) are not clients and are skipped.
 *
 *   php scripts/backfill-client-preferences.php            # show what would be inserted
 *   php scripts/backfill-client-preferences.php --apply
 */

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';
$app = require_once $base . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$apply = in_array('--apply', $argv, true);

/** The defaults the form ships — one place here, mirroring AddNewClient's properties. */
$defaults = [
    'email_pref_general' => '1',
    'email_pref_invoice' => '1',
    'email_pref_support' => '1',
    'email_pref_product' => '1',
    'email_pref_domain' => '1',
    'email_pref_affiliate' => '1',
    'setting_late_fees' => '1',
    'setting_overdue_notices' => '1',
    'setting_tax_exempt' => '0',
    'setting_separate_invoices' => '0',
    'setting_disable_cc' => '0',
    'setting_marketing_optin' => '0',
    'setting_status_update' => '1',
    'setting_single_sign_on' => '1',
];

echo $apply ? 'Applying.' : 'Dry run — nothing will be written. Re-run with --apply.';
echo PHP_EOL, PHP_EOL;

$clients = User::query()->whereNull('role_id')->with('properties')->orderBy('id')->get();
$inserted = 0;

foreach ($clients as $client) {
    $held = $client->properties->pluck('key')->all();
    $missing = array_diff_key($defaults, array_flip($held));

    if ($missing === []) {
        continue;
    }

    printf("#%-4d %-36s +%d rows: %s\n", $client->id, $client->email, count($missing), implode(', ', array_keys($missing)));

    if ($apply) {
        foreach ($missing as $key => $value) {
            $client->properties()->create(['key' => $key, 'value' => $value]);
            $inserted++;
        }
    } else {
        $inserted += count($missing);
    }
}

printf(
    '%s%d client(s) checked, %d row(s) %s.%s',
    PHP_EOL,
    $clients->count(),
    $inserted,
    $apply ? 'inserted' : 'would be inserted',
    PHP_EOL,
);
