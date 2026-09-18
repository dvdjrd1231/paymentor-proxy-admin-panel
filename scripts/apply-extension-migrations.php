<?php

/**
 * Apply the migrations of every enabled extension.
 *
 * `php artisan migrate` only knows `database/migrations`. Extensions keep their own, and
 * core runs those from `installed()`, which fires when an extension is *enabled* — not on
 * deploy. So a migration added to an extension sat unapplied after a deploy that cheerfully
 * reported "Nothing to migrate", and the column it added was missing until someone ran it by
 * hand. That happened twice on 2026-09-17/18 (recurrence limits on billable items, and the
 * description on transactions).
 *
 * Run from scripts/deploy.sh via `artisan tinker`. Idempotent: the migrator skips anything
 * already recorded in the `migrations` table, so running it on every deploy is free.
 */

use App\Helpers\ExtensionHelper;
use App\Models\Extension;

$applied = 0;
$failed = [];

foreach (Extension::where('enabled', true)->get() as $extension) {
    try {
        // The same call core makes when an extension is switched on.
        ExtensionHelper::call($extension, 'installed', mayFail: true);
        $applied++;
    } catch (\Throwable $e) {
        // One extension's bad migration must not stop the rest, and must not fail the
        // deploy silently either — it is named here and the deploy log carries it.
        $failed[] = $extension->name . ': ' . $e->getMessage();
    }
}

echo 'Extension migrations checked for ' . $applied . ' enabled '
    . ($applied === 1 ? 'extension' : 'extensions') . '.' . PHP_EOL;

foreach ($failed as $line) {
    echo '  FAILED  ' . $line . PHP_EOL;
}

if ($failed) {
    echo '  ^ these extensions may be missing schema changes.' . PHP_EOL;
}
