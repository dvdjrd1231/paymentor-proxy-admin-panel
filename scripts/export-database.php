<?php

/**
 * Export the local SQLite database to MySQL/MariaDB-compatible SQL.
 *
 *   php scripts/export-database.php [--clean] [--out=FILE]
 *
 *   --clean   omit local test data (test services, invoices, transactions,
 *             provisioning failures and test users).
 *             Keeps settings, roles, currencies, notification templates,
 *             categories/products/plans and the extension registry.
 *   --out     output file (default: database/export/paymenter-<mode>.sql)
 *
 * The output is **data only** — no CREATE TABLE. Build the schema on the target
 * with `php artisan migrate --seed`, which emits correct MySQL DDL for the
 * server you are actually running, then import this file. Converting SQLite DDL
 * to MySQL by hand loses indexes, foreign keys and column widths; letting the
 * migrations do it does not.
 *
 * Import order on the VPS matters:
 *   1. php artisan migrate --seed          (core schema)
 *   2. enable the extensions in Admin -> Extensions
 *      (their migrations create gateway_rules, payment_fee_rules,
 *       provisioning_operations, canned_responses, ticket_notes)
 *   3. mysql -u paymenter -p paymenter < paymenter-clean.sql
 *
 * Importing before step 2 fails on the extension tables, because those are created
 * by each extension's installed() hook rather than by artisan migrate.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$sqlitePath = $root . '/database/database.sqlite';

if (!is_file($sqlitePath)) {
    fwrite(STDERR, "No SQLite database at {$sqlitePath}\n");
    exit(1);
}

$clean = in_array('--clean', $argv, true);
$out = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--out=')) {
        $out = substr($arg, 6);
    }
}
$out ??= $root . '/database/export/paymenter-' . ($clean ? 'clean' : 'full') . '.sql';

@mkdir(dirname($out), 0775, true);

$db = new PDO('sqlite:' . $sqlitePath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

/** Tables that only ever hold local runtime state — never worth moving. */
const ALWAYS_SKIP = [
    'migrations',           // rebuilt by `artisan migrate`
    'sqlite_sequence',      // SQLite internal
    'sessions', 'jobs', 'job_batches', 'failed_jobs', 'cache', 'cache_locks',
    'user_sessions', 'user_authentication_logs',
    'debug_logs', 'audits', 'audit_logs', 'email_logs', 'notifications',
    'cron_stats',
];

/**
 * Local test data — excluded by --clean.
 *
 * `users` is here deliberately: exporting it would carry a known password hash into
 * production. Create the first admin on the target with `php artisan app:user:create`.
 */
const TEST_DATA = [
    'users',
    'services', 'service_configs', 'service_cancellations', 'service_upgrades',
    'invoices', 'invoice_items', 'invoice_transactions', 'invoice_snapshots',
    'provisioning_operations', 'properties', 'orders', 'carts', 'cart_items',
    'tickets', 'ticket_messages', 'ticket_message_attachments', 'ticket_mail_logs',
    'credits',
];

$skip = ALWAYS_SKIP;
if ($clean) {
    $skip = array_merge($skip, TEST_DATA);
}

$tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")
    ->fetchAll(PDO::FETCH_COLUMN);

$fh = fopen($out, 'w');

fwrite($fh, "-- Paymenter data export (" . ($clean ? 'clean — no test data' : 'full — includes local test data') . ")\n");
fwrite($fh, "-- Generated from the local SQLite database.\n");
fwrite($fh, "-- Import AFTER `php artisan migrate --seed` on the target server.\n");
fwrite($fh, "--\n-- mysql -u <user> -p <database> < " . basename($out) . "\n\n");
fwrite($fh, "SET NAMES utf8mb4;\n");
fwrite($fh, "SET FOREIGN_KEY_CHECKS=0;\n");
// NO_BACKSLASH_ESCAPES makes MySQL treat backslashes literally and single quotes as
// doubled ('') — the SQL standard, and identical to SQLite. Values here contain
// Windows paths and CSS/markdown with backslashes, so this keeps them intact and
// lets the dump be verified without a MySQL server.
fwrite($fh, "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO,NO_BACKSLASH_ESCAPES';\n\n");

$rowsTotal = 0;
$tablesWritten = 0;

foreach ($tables as $table) {
    if (in_array($table, $skip, true)) {
        continue;
    }

    $rows = $db->query('SELECT * FROM "' . $table . '"')->fetchAll(PDO::FETCH_ASSOC);

    // Per-deployment credentials must never travel. Server and Gateway settings hold
    // the panel URL/token and the gateway API keys — on a clean export keep only the
    // global settings (theme, currency, company details) and let the target be
    // configured in its own admin panel.
    if ($clean && $table === 'settings') {
        $rows = array_values(array_filter(
            $rows,
            fn ($r) => in_array($r['settingable_type'] ?? null, [null, '', 'App\Models\Product'], true)
        ));
    }

    // Likewise the Server row itself points at whichever panel was configured locally.
    if ($clean && $table === 'extensions') {
        $rows = array_values(array_filter(
            $rows,
            fn ($r) => !in_array($r['type'] ?? '', ['server', 'gateway'], true)
        ));
    }

    if (!$rows) {
        continue;
    }

    $columns = array_keys($rows[0]);
    $quotedCols = implode(', ', array_map(fn ($c) => '`' . $c . '`', $columns));

    fwrite($fh, "--\n-- {$table} (" . count($rows) . " rows)\n--\n");
    fwrite($fh, "DELETE FROM `{$table}`;\n");

    // One INSERT per row. The dataset is small, and a single-statement-per-line file
    // is unambiguous for any parser — including hand-editing before import.
    foreach (array_chunk($rows, 1) as $chunk) {
        $values = [];
        foreach ($chunk as $row) {
            $cells = [];
            foreach ($columns as $col) {
                $v = $row[$col];
                if ($v === null) {
                    $cells[] = 'NULL';
                } elseif (is_int($v) || is_float($v)) {
                    $cells[] = (string) $v;
                } else {
                    // Standard SQL escaping: double the single quotes and leave
                    // everything else (backslashes, newlines) literal. Combined with
                    // NO_BACKSLASH_ESCAPES this is valid in MySQL/MariaDB and is also
                    // exactly SQLite's syntax, so the file can be verified locally
                    // instead of being taken on trust.
                    //
                    // Values legitimately contain single quotes — the seeded mail CSS
                    // has font stacks like 'Segoe UI' — so getting this wrong silently
                    // truncates strings and corrupts the dump.
                    $cells[] = "'" . str_replace("'", "''", (string) $v) . "'";
                }
            }
            $values[] = '(' . implode(', ', $cells) . ')';
        }

        fwrite($fh, "INSERT INTO `{$table}` ({$quotedCols}) VALUES\n" . implode(",\n", $values) . ";\n");
    }

    fwrite($fh, "\n");
    $rowsTotal += count($rows);
    $tablesWritten++;
}

fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
fclose($fh);

printf("Wrote %s\n  %d tables, %d rows, %s\n", $out, $tablesWritten, $rowsTotal, $clean ? 'test data excluded' : 'ALL data including test records');
