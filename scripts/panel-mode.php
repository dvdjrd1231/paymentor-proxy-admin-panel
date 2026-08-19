<?php

/**
 * Switch the ProxyPanel module between the real panel and the bundled mock panel.
 *
 * The real panel currently lists no locations, so provisioning cannot complete and every
 * buy-flow demo dies at the last step. The mock (`scripts/mock-proxy-panel.php`) implements
 * the same documented API with test locations in stock, which lets the entire journey —
 * order → payment → provisioning → panel confirmation → active service — run today.
 * When the client adds real locations, `--real` puts everything back in one command.
 *
 *   php scripts/panel-mode.php               # show which panel is in use, and whether it answers
 *   php scripts/panel-mode.php --mock        # point at the mock (starts it if needed)
 *   php scripts/panel-mode.php --real        # restore the real panel settings, stop the mock
 *
 * Safe by construction:
 *  - the real api_url/api_token rows are copied aside (value AND encrypted flag, raw —
 *    an encrypted token is never decrypted here) before the first switch, and restored
 *    verbatim by --real. Running --mock twice cannot overwrite the backup with mock values.
 *  - a callback_secret is generated only if none exists; an existing secret is never touched.
 *  - the mock listens on 127.0.0.1 only — it is not reachable from outside the container.
 *
 * The mock runs inside the app container and dies with it. After a container restart,
 * re-run `--mock` (idempotent) to bring it back; until then provisioning fails loudly and
 * retries from Admin → Provisioning work once the mock is up again.
 */
$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';
$app = require_once $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Server;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

const MOCK_URL = 'http://127.0.0.1:9100/v0/services';
const MOCK_TOKEN = 'test-token';
const BACKUP_SUFFIX = '_real_backup';

$mode = in_array('--mock', $argv, true) ? 'mock' : (in_array('--real', $argv, true) ? 'real' : 'status');

$server = Server::where('extension', 'ProxyPanel')->first();
if (!$server) {
    echo "No ProxyPanel server is configured.\n";
    exit(1);
}

$row = fn (string $key) => DB::table('settings')
    ->where('settingable_type', Server::class)
    ->where('settingable_id', $server->id)
    ->where('key', $key)->first();

$put = function (string $key, ?string $value, int $encrypted = 0) use ($server) {
    $exists = DB::table('settings')
        ->where('settingable_type', Server::class)
        ->where('settingable_id', $server->id)->where('key', $key)->exists();

    if ($exists) {
        DB::table('settings')->where('settingable_type', Server::class)
            ->where('settingable_id', $server->id)->where('key', $key)
            ->update(['value' => $value, 'encrypted' => $encrypted, 'updated_at' => now()]);
    } else {
        DB::table('settings')->insert([
            'settingable_type' => Server::class, 'settingable_id' => $server->id,
            'key' => $key, 'value' => $value, 'type' => 'string', 'encrypted' => $encrypted,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
};

$mockRunning = fn () => @fsockopen('127.0.0.1', 9100, $e, $s, 1) !== false;

$startMock = function () use ($base, $mockRunning) {
    if ($mockRunning()) {
        return true;
    }

    shell_exec('MOCK_PANEL_TOKEN=' . MOCK_TOKEN . ' nohup php -S 127.0.0.1:9100 '
        . escapeshellarg($base . '/scripts/mock-proxy-panel.php') . ' > /tmp/mock-panel.log 2>&1 &');
    usleep(700_000);

    return $mockRunning();
};

$current = $row('api_url');
$onMock = $current && $current->value === MOCK_URL;

// ── Status ───────────────────────────────────────────────────────────────────────────────
if ($mode === 'status') {
    printf("Panel in use : %s (%s)\n", $onMock ? 'MOCK' : 'REAL', $current->value ?? '(unset)');
    printf("Mock process : %s\n", $mockRunning() ? 'running on 127.0.0.1:9100' : 'not running');
    printf("Real backup  : %s\n", $row('api_url' . BACKUP_SUFFIX) ? 'saved' : '(none yet)');

    // Ask the configured panel for locations — the question the whole flow hangs on.
    try {
        $token = $row('api_token');
        $plain = $token && $token->encrypted
            ? \Illuminate\Support\Facades\Crypt::decryptString($token->value)
            : ($token->value ?? '');
        $r = \Illuminate\Support\Facades\Http::withHeaders(['Panel' => $plain, 'Accept' => 'application/json'])
            ->timeout(10)->get(rtrim($current->value ?? '', '/') . '/locations');
        printf("GET /locations: HTTP %d  %s\n", $r->status(), substr($r->body(), 0, 100));
    } catch (\Throwable $e) {
        printf("GET /locations: unreachable — %s\n", substr($e->getMessage(), 0, 80));
    }
    exit(0);
}

// ── Switch to the mock ───────────────────────────────────────────────────────────────────
if ($mode === 'mock') {
    if (!$startMock()) {
        echo "Could not start the mock panel — see /tmp/mock-panel.log\n";
        exit(1);
    }
    echo "[ ok ] mock panel running on 127.0.0.1:9100\n";

    foreach (['api_url', 'api_token'] as $key) {
        $live = $row($key);

        // Never let a second --mock overwrite the backup with mock values.
        if ($live && $live->value !== ($key === 'api_url' ? MOCK_URL : MOCK_TOKEN) && !$row($key . BACKUP_SUFFIX)) {
            $put($key . BACKUP_SUFFIX, $live->value, (int) $live->encrypted);
            echo "[ ok ] {$key} backed up\n";
        }
    }

    $put('api_url', MOCK_URL, 0);
    $put('api_token', MOCK_TOKEN, 0);

    // The activation callback needs a shared secret; generate one only if none exists.
    if (!($row('callback_secret')->value ?? null)) {
        $put('callback_secret', Str::random(40), 0);
        echo "[ ok ] callback_secret generated (was empty)\n";
    }

    echo "[ ok ] ProxyPanel now points at the MOCK. Restore with: php scripts/panel-mode.php --real\n";
    exit(0);
}

// ── Restore the real panel ───────────────────────────────────────────────────────────────
foreach (['api_url', 'api_token'] as $key) {
    $backup = $row($key . BACKUP_SUFFIX);

    if (!$backup) {
        echo "[ !! ] no backup for {$key} — leaving it as-is\n";
        continue;
    }

    $put($key, $backup->value, (int) $backup->encrypted);
    DB::table('settings')->where('settingable_type', Server::class)
        ->where('settingable_id', $server->id)->where('key', $key . BACKUP_SUFFIX)->delete();
    echo "[ ok ] {$key} restored\n";
}

shell_exec("pkill -f 'mock-proxy-panel.php' 2>/dev/null");
echo "[ ok ] mock stopped. ProxyPanel points at the REAL panel again.\n";
