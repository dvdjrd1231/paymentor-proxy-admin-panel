<?php

/**
 * Mock proxy panel — a development/test double implementing the panel's documented
 * RotatingServices REST API (`api.md`), so the whole provisioning lifecycle and its
 * failure paths can be exercised without touching the client's production panel.
 *
 *   php -S 127.0.0.1:9000 scripts/mock-proxy-panel.php
 *
 * Point the ProxyPanel server extension at:
 *   Panel API URL  http://127.0.0.1:9000/v0/services
 *   Panel Token    test-token          (override with MOCK_PANEL_TOKEN)
 *
 * Implements the endpoint surface the client's WHMCS module calls in production (scope
 * §8 "convert the existing WHMCS proxyPanel module"), which is a superset of `api.md`:
 *   GET  /v0/services/{id}                       service info
 *   POST /v0/services/newIpv6                    create (legacy name used by the module)
 *   GET  /v0/services/plans · /locations         catalogue for Plan / Region dropdowns
 *   GET  /v0/services/stop/{id} · /start/{id}    suspend / unsuspend
 *   POST /v0/services/credentials/{id}           username + password
 *   POST /v0/services/auth_ips/{id}              authorized IPs
 *   GET  /v0/services/rotate/{id}/1              manual rotation
 *   POST /v0/services/renew/{id}                 renew, clears rotation counter
 *   GET  /v0/services/extend/{id}/{unixts}       set expiration
 *   GET  /v0/services/expand/{id}/{amount}       add proxies
 *   GET  /v0/services/shrink/{id}/{amount}       remove proxies
 *   GET  /v0/services/cancel/{id}                terminate
 *   POST /v0/services/aa/{id}                    authorize[] / authenticate[]
 *   GET  /v0/services/blacklist/{bid}/{status}   blacklist on/off
 *   GET  /v0/services/reboot/{id}[/hard]         reboot
 *   GET  /v0/services/rotate/{id}                manual rotation (counter enforced)
 *   GET  /v0/services/setRotate/{id}/{minutes}   automatic rotation interval
 *
 * Fault injection — to prove failures surface in the admin and never silently activate:
 *   curl "http://127.0.0.1:9000/_control/fail?on=1"    # every API call returns 500
 *   curl "http://127.0.0.1:9000/_control/fail?on=0"
 *   curl "http://127.0.0.1:9000/_control/state"        # dump provisioned services
 *   curl "http://127.0.0.1:9000/_control/reset"
 *   curl "http://127.0.0.1:9000/_control/callback?id=1000&status=ok&url=<paymenter>&secret=<secret>"
 *
 * NOT for production — no real authentication or isolation.
 */

declare(strict_types=1);

const DEFAULT_TOKEN = 'test-token';
const MAX_AUTH_IPS = 3;

$stateFile = getenv('MOCK_PANEL_STATE') ?: sys_get_temp_dir() . '/mock-proxy-panel.json';
$token = getenv('MOCK_PANEL_TOKEN') ?: DEFAULT_TOKEN;

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

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

/** The panel's convention: HTTP 200 with {status:error} for API-level errors. */
function panelError(string $description): never
{
    respond(['status' => 'error', 'description' => $description]);
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

function makeProxies(string $id, int $amount): array
{
    $ips = [];
    for ($i = 0; $i < $amount; $i++) {
        $ips[] = [
            'ip' => sprintf('2a01:4f8:%s:%04x::%d', substr($id, -4), random_int(0, 0xFFFF), $i + 1),
            'port' => 10000 + $i,
        ];
    }

    return $ips;
}

$state = loadState($stateFile);

// ── Control plane (no auth — local test tool) ────────────────────────────────
if (str_starts_with($path, '/_control')) {
    switch ($path) {
        case '/_control/fail':
            $state['fail'] = (bool) (int) ($_GET['on'] ?? 1);
            saveState($stateFile, $state);
            respond(['status' => 'ok', 'failing' => $state['fail']]);

        case '/_control/state':
            respond(['status' => 'ok', 'failing' => $state['fail'], 'services' => $state['services']]);

        case '/_control/reset':
            saveState($stateFile, ['next_id' => 1000, 'fail' => false, 'services' => []]);
            respond(['status' => 'ok', 'description' => 'state reset']);

        case '/_control/callback':
            // Fire a panel->Paymenter callback, the way the real panel would.
            $url = (string) ($_GET['url'] ?? '');
            $secret = (string) ($_GET['secret'] ?? '');
            $body = json_encode([
                'id' => (string) ($_GET['id'] ?? ''),
                'status' => (string) ($_GET['status'] ?? 'ok'),
                'description' => (string) ($_GET['description'] ?? ''),
            ], JSON_UNESCAPED_SLASHES);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'X-Panel-Signature: ' . hash_hmac('sha256', $body, $secret),
                ],
            ]);
            $result = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            respond(['status' => 'ok', 'sent' => $body, 'response_code' => $code, 'response' => $result]);

        default:
            respond(['status' => 'error', 'description' => 'unknown control endpoint'], 404);
    }
}

// ── Auth: the panel authenticates with a `Panel:` header ────────────────────
if (!hash_equals($token, (string) ($_SERVER['HTTP_PANEL'] ?? ''))) {
    respond(['status' => 'error', 'description' => 'Invalid panel token'], 401);
}

// ── Fault injection ─────────────────────────────────────────────────────────
if (!empty($state['fail'])) {
    respond(['status' => 'error', 'description' => 'Mock panel is in forced-failure mode'], 500);
}

$route = preg_replace('#^/v0/services#', '', $path) ?: '/';
$segments = array_values(array_filter(explode('/', $route), fn ($s) => $s !== ''));
$verb = $segments[0] ?? '';
$id = $segments[1] ?? '';

// ── Catalogue (Plan / Region dropdowns) ─────────────────────────────────────
if ($verb === 'plans') {
    respond(['status' => 'ok', 'data' => [
        ['tag' => 'rot-100', 'name' => 'Rotating 100'],
        ['tag' => 'rot-500', 'name' => 'Rotating 500'],
    ]]);
}

if ($verb === 'locations') {
    respond(['status' => 'ok', 'data' => [
        ['tag' => 'us-nyc', 'name' => 'United States - New York'],
        ['tag' => 'nl-ams', 'name' => 'Netherlands - Amsterdam'],
    ]]);
}

// ── Create ──────────────────────────────────────────────────────────────────
if ($method === 'POST' && ($verb === 'newIpv6' || $verb === 'new')) {
    $body = jsonBody();

    // The WHMCS module sends location_name; api.md calls the same field server_tag.
    $body['server_tag'] = $body['server_tag'] ?? ($body['location_name'] ?? null);

    foreach (['client_id', 'plan_tag', 'amount'] as $required) {
        if (!isset($body[$required]) || $body[$required] === '' || $body[$required] === null) {
            panelError("Missing required field: {$required}");
        }
    }

    $newId = (string) $state['next_id']++;
    $amount = max(1, (int) $body['amount']);

    $state['services'][$newId] = [
        'id' => $newId,
        'client_id' => $body['client_id'],
        'plan_tag' => $body['plan_tag'],
        'server_tag' => $body['server_tag'],
        'bwlimit' => $body['bwlimit'] ?? null,
        'expiration' => $body['expiration'] ?? null,
        'plan_manual_rotate' => 1,
        'plan_change_rotate' => 1,
        'plan_max_rotate' => 10,
        'rotation_counter' => 0,
        'rotation_time' => 0,
        'api_key' => bin2hex(random_bytes(12)),
        'authorize' => array_slice((array) ($body['authorize'] ?? []), 0, MAX_AUTH_IPS),
        'authenticate' => $body['authenticate'] ?? null,
        'blacklists' => [],
        'ips' => makeProxies($newId, $amount),
    ];
    saveState($stateFile, $state);

    respond(['status' => 'ok'] + $state['services'][$newId]);
}

// ── Everything below needs an existing service ──────────────────────────────
$needsService = in_array($verb, ['renew', 'extend', 'expand', 'shrink', 'cancel', 'aa', 'reboot',
    'rotate', 'setRotate', 'stop', 'start', 'credentials', 'auth_ips'], true);

if ($needsService && !isset($state['services'][$id])) {
    // Cancelling something already gone is a no-op, which terminateServer relies on.
    if ($verb === 'cancel') {
        respond(['status' => 'ok', 'description' => 'already removed']);
    }
    panelError('Unknown service ' . $id);
}

if ($verb === 'stop' || $verb === 'start') {
    $state['services'][$id]['suspended'] = ($verb === 'stop');
    saveState($stateFile, $state);
    respond(['status' => 'ok'] + $state['services'][$id]);
}

if ($method === 'POST' && $verb === 'credentials') {
    $body = jsonBody();
    $state['services'][$id]['authenticate'] = [
        'username' => $body['username'] ?? null,
        'password' => $body['password'] ?? null,
    ];
    saveState($stateFile, $state);
    respond(['status' => 'ok'] + $state['services'][$id]);
}

if ($method === 'POST' && $verb === 'auth_ips') {
    $body = jsonBody();
    $ips = (array) ($body['ips'] ?? []);
    if (count($ips) > MAX_AUTH_IPS) {
        panelError('At most ' . MAX_AUTH_IPS . ' authorized IPs');
    }
    $state['services'][$id]['authorize'] = array_values($ips);
    saveState($stateFile, $state);
    respond(['status' => 'ok'] + $state['services'][$id]);
}

if ($verb === 'cancel') {
    unset($state['services'][$id]);
    saveState($stateFile, $state);
    respond(['status' => 'ok', 'description' => 'cancelled']);
}

if ($verb === 'renew') {
    $state['services'][$id]['rotation_counter'] = 0;
    saveState($stateFile, $state);
    respond(['status' => 'ok'] + $state['services'][$id]);
}

if ($verb === 'extend') {
    if (!isset($segments[2]) || !ctype_digit($segments[2])) {
        panelError('extend requires a unix timestamp');
    }
    $state['services'][$id]['expiration'] = (int) $segments[2];
    saveState($stateFile, $state);
    respond(['status' => 'ok', 'expiration' => $state['services'][$id]['expiration']]);
}

if ($verb === 'expand' || $verb === 'shrink') {
    $amount = (int) ($segments[2] ?? 0);
    if ($amount < 1) {
        panelError($verb . ' requires a positive amount');
    }

    $current = $state['services'][$id]['ips'];

    if ($verb === 'expand') {
        $state['services'][$id]['ips'] = array_merge($current, makeProxies($id, $amount));
    } else {
        if ($amount >= count($current)) {
            panelError('Cannot shrink below one proxy');
        }
        $state['services'][$id]['ips'] = array_slice($current, 0, count($current) - $amount);
    }

    saveState($stateFile, $state);
    respond(['status' => 'ok'] + $state['services'][$id]);
}

if ($method === 'POST' && $verb === 'aa') {
    $body = jsonBody();

    if (isset($body['authorize'])) {
        if (count((array) $body['authorize']) > MAX_AUTH_IPS) {
            panelError('At most ' . MAX_AUTH_IPS . ' authorized IPs');
        }
        $state['services'][$id]['authorize'] = array_values((array) $body['authorize']);
    }

    if (isset($body['authenticate'])) {
        $state['services'][$id]['authenticate'] = $body['authenticate'];
    }

    saveState($stateFile, $state);
    respond(['status' => 'ok'] + $state['services'][$id]);
}

if ($verb === 'rotate') {
    // The WHMCS module calls /rotate/{id}/1 — the trailing segment is accepted and ignored.
    $svc = &$state['services'][$id];

    if (!empty($svc['plan_max_rotate']) && $svc['rotation_counter'] >= $svc['plan_max_rotate']) {
        panelError('Rotation limit reached');
    }

    $svc['rotation_counter']++;
    $svc['ips'] = makeProxies($id, count($svc['ips']));
    unset($svc);
    saveState($stateFile, $state);
    respond(['status' => 'ok'] + $state['services'][$id]);
}

if ($verb === 'setRotate') {
    $state['services'][$id]['rotation_time'] = (int) ($segments[2] ?? 0);
    saveState($stateFile, $state);
    respond(['status' => 'ok', 'rotation_time' => $state['services'][$id]['rotation_time']]);
}

if ($verb === 'reboot') {
    respond(['status' => 'ok', 'description' => ($segments[2] ?? '') === 'hard' ? 'hard reboot' : 'reboot']);
}

if ($verb === 'blacklist') {
    $blacklistId = $segments[1] ?? '';
    $status = $segments[2] ?? '';
    respond(['status' => 'ok', 'blacklist_id' => $blacklistId, 'blacklist_status' => $status]);
}

// ── Service info: GET /{id} ─────────────────────────────────────────────────
if (count($segments) === 1 && ctype_digit($segments[0])) {
    if (!isset($state['services'][$segments[0]])) {
        panelError('Unknown service ' . $segments[0]);
    }

    respond(['status' => 'ok'] + $state['services'][$segments[0]]);
}

respond(['status' => 'error', 'description' => 'Unknown endpoint: ' . $route], 404);
