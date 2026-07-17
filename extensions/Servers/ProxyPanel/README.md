# ProxyPanel Provisioning Module

Native Paymenter **Server** module for the IPv6 proxy admin panel — replacement for the legacy
WHMCS *proxyPanel* module. Idempotent lifecycle (create/suspend/unsuspend/terminate/upgrade) with
retry, per-service locks, and logging.

Full documentation: [`docs/modules/proxypanel.md`](../../../docs/modules/proxypanel.md).

> **Status:** lifecycle complete; API endpoints marked `@api` in `ProxyPanel.php` are wired once
> the admin-panel API spec is provided.

**Enable:** `php artisan app:extension:enable Servers/ProxyPanel`, then create a Server and set the
API URL + API Key.
