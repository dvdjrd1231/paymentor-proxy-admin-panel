<?php

/**
 * Remove data left behind by the end-to-end test scripts.
 *
 * The suites register throwaway customers at `@example.test` and drive real invoices through
 * them. `test-full-workflow.php` tears its own down; the others now do too, but a run that
 * fails part way deliberately leaves its data in place so it can be inspected. This clears
 * whatever is left once the diagnosis is done.
 *
 * It also sweeps two things that accumulate quietly:
 *
 *  - **Invoice PDFs with no invoice.** Deleting an invoice does not delete its rendered PDF,
 *    so the directory outgrows the invoice table. A PDF whose invoice is gone can never be
 *    served — it is only disk.
 *  - **Failed jobs.** Worth reading before clearing: `CreateJob` failures are expected while
 *    the panel lists no locations, and `NotificationCreatedListener` fails when its
 *    notification row was cascaded away with a deleted test user. Keeping known failures
 *    around hides new ones.
 *
 * Nothing here touches a real customer: only `@example.test` accounts and records already
 * unreachable are considered.
 *
 *   php scripts/cleanup-test-data.php            # show what it would remove
 *   php scripts/cleanup-test-data.php --apply
 */
$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';
$app = require_once $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Invoice;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$apply = in_array('--apply', $argv, true);
echo $apply ? "Applying.\n\n" : "Dry run — nothing will be written. Re-run with --apply.\n\n";

/** Delete child rows only from tables that exist, so a schema difference cannot half-apply. */
$purge = function (array $tables, string $column, int $id) use ($apply): void {
    if (!$apply) {
        return;
    }

    foreach ($tables as $table) {
        if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
            DB::table($table)->where($column, $id)->delete();
        }
    }
};

// ── Throwaway accounts ───────────────────────────────────────────────────────────────────
$testUsers = User::where('email', 'like', '%@example.test')->get();
$invoices = Invoice::whereIn('user_id', $testUsers->pluck('id'))->get();
$services = Service::whereIn('user_id', $testUsers->pluck('id'))->get();

printf("[ %s ] %d test user(s): %s\n", $apply ? ' ok ' : 'todo',
    $testUsers->count(), $testUsers->pluck('email')->implode(', ') ?: '(none)');
printf("[ %s ] %d invoice(s) and %d service(s) belonging to them\n",
    $apply ? ' ok ' : 'todo', $invoices->count(), $services->count());

// ── Services that were never ordered ─────────────────────────────────────────────────────
// A service with no order was created by a test run rather than bought, so removing it
// cannot destroy a purchase.
$orphans = Service::whereDoesntHave('order')->get();

foreach ($orphans as $s) {
    printf("[ %s ] order-less service #%d — %s, owner %s, status %s\n", $apply ? ' ok ' : 'todo',
        $s->id, optional($s->product)->name ?? '?', optional($s->user)->email ?? '(none)', $s->status);
}

// ── Invoice PDFs with no invoice ─────────────────────────────────────────────────────────
$pdfDir = $base . '/storage/app/invoices';
$onDisk = glob($pdfDir . '/INV-*.pdf') ?: [];
$goneIds = $invoices->pluck('id')->all();
$liveIds = Invoice::pluck('id')->all();
$stalePdfs = [];

foreach ($onDisk as $path) {
    if (!preg_match('/INV-(\d+)\.pdf$/', $path, $m)) {
        continue;
    }

    $id = (int) $m[1];

    if (in_array($id, $goneIds, true) || !in_array($id, $liveIds, true)) {
        $stalePdfs[] = $path;
    }
}

printf("[ %s ] %d unreachable invoice PDF(s) of %d on disk (%d invoice(s) exist)\n",
    $apply ? ' ok ' : 'todo', count($stalePdfs), count($onDisk), count($liveIds));

// ── Failed jobs ──────────────────────────────────────────────────────────────────────────
$failed = DB::table('failed_jobs')->count();
$reasons = [];

foreach (DB::table('failed_jobs')->get() as $job) {
    $name = json_decode($job->payload, true)['displayName'] ?? '?';
    $reasons[$name] = ($reasons[$name] ?? 0) + 1;
}

printf("[ %s ] %d failed job(s): %s\n", $apply ? ' ok ' : 'todo', $failed,
    $failed ? implode(', ', array_map(fn ($k, $v) => "{$v}× " . class_basename($k), array_keys($reasons), $reasons)) : 'none');

if (!$apply) {
    echo "\nNothing was written. Re-run with --apply.\n";
    exit(0);
}

// ── Apply ────────────────────────────────────────────────────────────────────────────────
foreach ($stalePdfs as $path) {
    @unlink($path);
}

foreach ($invoices as $invoice) {
    $purge(['invoice_items', 'invoice_transactions', 'invoice_snapshots'], 'invoice_id', $invoice->id);
    $invoice->delete();
}

foreach ($services->concat($orphans)->unique('id') as $service) {
    $purge(['service_cancellations', 'service_configs', 'service_upgrades'], 'service_id', $service->id);

    // Properties are polymorphic rather than a service_* table.
    foreach (['properties', 'custom_properties'] as $table) {
        if (Schema::hasTable($table) && Schema::hasColumn($table, 'model_id')) {
            DB::table($table)->where('model_type', Service::class)->where('model_id', $service->id)->delete();
        }
    }

    $service->delete();
}

foreach ($testUsers as $user) {
    $purge(['credits', 'tickets', 'notifications'], 'user_id', $user->id);

    foreach (['properties', 'custom_properties'] as $table) {
        if (Schema::hasTable($table) && Schema::hasColumn($table, 'model_id')) {
            DB::table($table)->where('model_type', User::class)->where('model_id', $user->id)->delete();
        }
    }

    $user->forceDelete();
}

DB::table('failed_jobs')->delete();

printf("\nDone. users: %d   services: %d   invoices: %d   PDFs: %d   failed jobs: %d\n",
    User::count(), Service::count(), Invoice::count(),
    count(glob($pdfDir . '/INV-*.pdf') ?: []), DB::table('failed_jobs')->count());
