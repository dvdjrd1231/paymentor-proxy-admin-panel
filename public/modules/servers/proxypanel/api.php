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
| See docs/CORE-TOUCHPOINTS.md.
|
*/

require __DIR__ . '/../../../index.php';
