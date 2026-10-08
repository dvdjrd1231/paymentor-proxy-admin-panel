<?php

/*
|--------------------------------------------------------------------------
| ProxyPanel customer API — entry point
|--------------------------------------------------------------------------
|
| Customers' scripts call /modules/servers/proxypanel/api.php, the address the WHMCS module
| served (Leandro, 2026-10-08: "tudo igual ao whmcs"), and nginx hands anything ending in
| .php straight to PHP-FPM. With no file here, FPM answered "File not found." and the route
| registered in extensions/Servers/ProxyPanel/routes.php was never reached.
|
| So this file exists only to be found. It hands the request to Laravel, which routes it on
| its URI exactly as it would any other — the logic lives in ProxyPanelApiController, not
| here, and nothing is duplicated.
|
| The two lines below are what make that routing work. Symfony derives the application's
| base URL from SCRIPT_NAME and strips it from the URI to get the path; left alone, that is
| this very file, so the path came out as "/" and every call was answered by the dashboard —
| a 302 to /login, not a word of JSON, and the route never consulted. Presenting the front
| controller instead leaves the full URI as the path, which is what the router matches on.
|
| See docs/CORE-TOUCHPOINTS.md.
|
*/

$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__, 3) . '/index.php';

require __DIR__ . '/../../../index.php';
