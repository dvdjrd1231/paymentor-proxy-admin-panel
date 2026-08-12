<?php

/**
 * Mock IPv6 proxy panel API — a development/test double for the real panel.
 *
 * It implements the endpoint contract that `extensions/Servers/ProxyPanel` speaks, so the
 * whole provisioning lifecycle (and its failure paths) can be exercised without touching
 * the client's production panel.
 *
 *   php -S 127.0.0.1:9000 scripts/mock-proxy-panel.php
 *
 * Point the ProxyPanel server extension at:
 *   Panel API URL  http://127.0.0.1:9000/v0/services
 *   Panel Token    test-token          (override with MOCK_PANEL_TOKEN)
 *
 * Fault injection — to prove that provisioning failures surface in the admin and never
 * silently activate an order:
 *   curl "http://127.0.0.1:9000/_control/fail?on=1"    # every API call now returns 500
 *   curl "http://127.0.0.1:9000/_control/fail?on=0"    # back to healthy
 *   curl "http://127.0.0.1:9000/_control/state"        # dump provisioned services
 *   curl "http://127.0.0.1:9000/_control/reset"        # wipe state
 *
 * State lives in a JSON file next to this script (MOCK_PANEL_STATE to relocate).
 * NOT for production use — there is no real authentication or isolation here.
 */

declare(strict_types=1);

const DEFAULT_TOKEN = 'test-token';

$stateFile = getenv('MOCK_PANEL_STATE') ?: sys_get_temp_dir() . '/mock-proxy-panel.json';
$token = getenv('MOCK_PANEL_TOKEN') ?: DEFAULT_TOKEN;

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

/** @return array{next_id:int, fail:bool, services:array<string,array>} */
function loadState(string $file): array
{
    $default = ['next_id' => 1000, 'fail' => false, 'services' => []];

    if (!is_file($file)) {
        return $default;
    }

    $data = json_decode((string) file_get_contents($file), true);

    return is_array($data) ? $data + $default : $default;
}

function saveState(string $file, array $state): void
{
    file_put_contents($file, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function respond(array $body, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}

/** The panel's own error convention: HTTP 200 with {status:error} for API-level errors. */
function panelError(string $description, int $code = 200): never
{
    respond(['status' => 'error', 'description' => $description], $code);
}

function jsonBody(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);

    if (is_array($data)) {
        return $data;
    }

    parse_str($raw, $form);

    return is_array($form) ? $form : [];
}

$state = loadState($stateFile);

// ── Control plane (no auth — local test tool) ────────────────────────────────
if (str_starts_with($path, '/_control')) {
    switch ($path) {
        case '/_control/fail':
            $state['fail'] = (bool) (int) ($_GET['on'] ?? 1);
            saveState($stateFile, $state);
            respond(['status' => 'ok', 'failing' => $state['fail']]);

            // no break — respond() exits
        case '/_control/state':
            respond(['status' => 'ok', 'failing' => $state['fail'], 'services' => $state['services']]);

        case '/_control/reset':
            $state = ['next_id' => 1000, 'fail' => false, 'services' => []];
            saveState($stateFile, $state);
            respond(['status' => 'ok', 'description' => 'state reset']);

        default:
            respond(['status' => 'error', 'description' => 'unknown control endpoint'], 404);
    }
}

// ── Auth: the real panel authenticates with a `Panel:` header ────────────────
$provided = $_SERVER['HTTP_PANEL'] ?? '';
if (!hash_equals($token, (string) $provided)) {
    respond(['status' => 'error', 'description' => 'Invalid panel token'], 401);
}

// ── Fault injection ─────────────────────────────────────────────────────────
if (!empty($state['fail'])) {
    respond(['status' => 'error', 'description' => 'Mock panel is in forced-failure mode'], 500);
}

// Everything below lives under the /v0/services prefix.
$route = preg_replace('#^/v0/services#', '', $path) ?: '/';
$segments = array_values(array_filter(explode('/', $route), fn ($s) => $s !== ''));

// ── Catalogue ───────────────────────────────────────────────────────────────
if ($route === '/plans') {
    respond(['status' => 'ok', 'data' => [
        ['tag' => 'ipv6-basic', 'name' => 'IPv6 Basic'],
        ['tag' => 'ipv6-pro', 'name' => 'IPv6 Pro'],
    ]]);
}

if ($route === '/locations') {
    respond(['status' => 'ok', 'data' => [
        ['tag' => 'us-east', 'name' => 'US East'],
        ['tag' => 'eu-west', 'name' => 'EU West'],
    ]]);
}

// ── Create ──────────────────────────────────────────────────────────────────
if ($method === 'POST' && $route === '/newIpv6') {
    $body = jsonBody();

    foreach (['client_id', 'plan_tag', 'location_name'] as $required) {
        if (empty($body[$required])) {
            panelError("Missing required field: {$required}");
        }
    }

    $id = (string) $state['next_id']++;
    $amount = max(1, (int) ($body['amount'] ?? 1));

    $ips = [];
    for ($i = 0; $i < $amount; $i++) {
        $ips[] = sprintf('2a01:4f8:%s:%04x::%d', substr($id, -4), random_int(0, 0xFFFF), $i + 1);
    }

    $state['services'][$id] = [
        'id' => $id,
        'client_id' => $body['client_id'],
        'plan_tag' => $body['plan_tag'],
        'location_name' => $body['location_name'],
        'amount' => $amount,
        'status' => 'active',
        'host' => 'gw-' . ($body['location_name'] ?? 'x') . '.mock-panel.test',
        'username' => $body['authenticate']['username'] ?? null,
        'password' => $body['authenticate']['password'] ?? null,
        'bwlimit' => $body['bwlimit'] ?? null,
        'ips' => $ips,
        'expires_at' => null,
    ];
    saveState($stateFile, $state);

    respond(['status' => 'ok', 'id' => $id, 'ips' => $ips, 'host' => $state['services'][$id]['host']]);
}

// ── Credentials ─────────────────────────────────────────────────────────────
if ($method === 'POST' && ($segments[0] ?? '') === 'credentials') {
    $id = $segments[1] ?? '';
    if (!isset($state['services'][$id])) {
        panelError('Unknown service ' . $id);
    }

    $body = jsonBody();
    $state['services'][$id]['username'] = $body['username'] ?? $state['services'][$id]['username'];
    $state['services'][$id]['password'] = $body['password'] ?? $state['services'][$id]['password'];
    saveState($stateFile, $state);

    respond(['status' => 'ok']);
}

// ── Lifecycle verbs: /<verb>/<id>[/<arg>] ───────────────────────────────────
$verbs = [
    'start' => 'active',
    'stop' => 'suspended',
    'cancel' => 'cancelled',
];

$verb = $segments[0] ?? '';
$id = $segments[1] ?? '';

if (isset($verbs[$verb])) {
    if (!isset($state['services'][$id])) {
        // Cancelling something that is already gone is a no-op, matching the real panel's
        // idempotent behaviour that ProxyPanel::terminateServer relies on.
        if ($verb === 'cancel') {
            respond(['status' => 'ok', 'description' => 'already removed']);
        }
        panelError('Unknown service ' . $id);
    }

    $state['services'][$id]['status'] = $verbs[$verb];
    saveState($stateFile, $state);

    respond(['status' => 'ok', 'id' => $id, 'state' => $verbs[$verb]]);
}

if (in_array($verb, ['reboot', 'rotate', 'setRotate', 'extend', 'renew'], true)) {
    if (!isset($state['services'][$id])) {
        panelError('Unknown service ' . $id);
    }

    if ($verb === 'extend' && isset($segments[2])) {
        $state['services'][$id]['expires_at'] = (int) $segments[2];
    }

    if ($verb === 'rotate') {
        foreach ($state['services'][$id]['ips'] as $i => $_) {
            $state['services'][$id]['ips'][$i] = sprintf('2a01:4f8:%s:%04x::%d', substr($id, -4), random_int(0, 0xFFFF), $i + 1);
        }
    }

    saveState($stateFile, $state);

    respond(['status' => 'ok', 'id' => $id]);
}

// ── Status lookup: /<id> ────────────────────────────────────────────────────
if (count($segments) === 1 && ctype_digit($segments[0])) {
    $id = $segments[0];
    if (!isset($state['services'][$id])) {
        panelError('Unknown service ' . $id);
    }

    // The service's own state goes in `data` — the top-level `status` is the panel's
    // ok/error envelope, so merging them here would hide one behind the other.
    respond(['status' => 'ok', 'data' => $state['services'][$id]]);
}

respond(['status' => 'error', 'description' => 'Unknown endpoint: ' . $route], 404);
