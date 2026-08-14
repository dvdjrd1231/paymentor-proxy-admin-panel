# ProxyPanel Provisioning Module

Native Paymenter **Server** module for the IPv6 proxy admin panel — replacement for the legacy
WHMCS *proxyPanel* module. Idempotent lifecycle (create/suspend/unsuspend/terminate/upgrade) with
retry, per-service locks, and logging.

Full documentation: [`docs/modules/proxypanel.md`](../../../docs/modules/proxypanel.md).

> **Status:** lifecycle complete; API endpoints marked `@api` in `ProxyPanel.php` are wired once
> the admin-panel API spec is provided.

**Enable:** enable **ProxyPanel** under **Admin → Extensions**, then create a Server and set the
API URL + API Key.

**Callback:** `POST /extensions/proxypanel/callback`, authenticated with the encrypted
**Callback Secret** (`X-Panel-Secret`, or `X-Panel-Signature` HMAC-SHA256 of the raw body).
Disabled while the secret is blank.

**Failures** are recorded via `Others/ProvisioningOps` and appear under
**Services → Provisioning** with a Retry button; a failed create reverts the service to
`pending` so an order is never silently active.
