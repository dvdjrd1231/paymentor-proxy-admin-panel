# ProxyPanel Provisioning Module

Native Paymenter **Server** (provisioning) module for the IPv6 proxy admin panel — the
replacement for the legacy WHMCS *proxyPanel* module.

- **Location:** `extensions/Servers/ProxyPanel/`
- **Type:** Server (provisioning)
- **Status:** Lifecycle complete; **API endpoints pending the admin-panel spec** (see *Wiring*).

## WHMCS → Paymenter mapping

| WHMCS function | Paymenter method | Notes |
|---|---|---|
| `CreateAccount` | `createServer` | idempotent — reuses existing remote id if present |
| `SuspendAccount` | `suspendServer` | |
| `UnsuspendAccount` | `unsuspendServer` | |
| `TerminateAccount` | `terminateServer` | idempotent — no-op if never provisioned |
| `ChangePackage` | `upgradeServer` | upgrade **and** downgrade |
| Client-area links | `getActions` | e.g. "Sync status" |
| Renewal | *(none)* | **Paymenter has no renew hook** — renewal is billing-driven; a paid invoice keeps the service active. We reconcile via `syncStatus`. |

## Configuration

### Module (admin → Servers → ProxyPanel settings)

| Setting | Description | Stored |
|---|---|---|
| **Admin Panel API URL** | Base URL of the panel API, e.g. `https://panel.example.com/api` | plain |
| **API Key** | Auth token for the panel | encrypted |

Use **Test connection** to verify (`GET {api_url}/ping`).

### Product (admin → Product → this server)

- **Proxy Plan** — plan/package id in the panel (dropdown from the panel).
- **Protocol** — HTTP(S) / SOCKS5.
- **Quantity** — proxies per service (overridable at checkout).
- **Allowed Location(s)** — restrict checkout locations (optional).

### Checkout (customer)

- **Location** and **Quantity**.

## Robustness

- **Idempotency** — each lifecycle op runs under a per-service/per-action lock (`proxypanel_lock`
  service property, 5-minute TTL) and short-circuits when the panel already reflects the target
  state. The remote id is stored as the `proxypanel_service_id` property.
- **Retry** — transient HTTP errors are retried (3×, 200 ms backoff) before failing.
- **Queue support** — provisioning is triggered by Paymenter's service lifecycle; long calls run
  on the queue worker (`php artisan queue:work`) with Laravel's built-in retry/backoff.
- **Logging / error handling** — every operation and failure is logged with `[ProxyPanel]`.
- **No hardcoded secrets** — the API key is an encrypted setting.

## Wiring the real API

The lifecycle methods are complete and should **not** need changes. When you provide the panel's
API spec, edit only the marked (`@api`) items in `ProxyPanel.php`:

1. **Endpoint paths** — the `ENDPOINT_*` constants (create / suspend / unsuspend / terminate /
   change-plan / status / plans / locations / ping).
2. **Auth header** — `request()` assumes `Authorization: Bearer <api_key>`; adjust if the panel
   uses a different scheme (e.g. `X-Api-Key`, HMAC-signed).
3. **Request payload keys** — the `createServer` payload (`external_ref`, `plan`, `protocol`,
   `location`, `quantity`, `email`) → the panel's create contract.
4. **Response field names** — where we read the new service id (`id` / `service_id`) and the plan/
   location list shapes in `fetchPlans()` / `fetchLocations()`.

### Information needed from the client

- Base URL and auth scheme.
- Request/response for: create, suspend, unsuspend, terminate, change-plan, status, list plans,
  list locations.
- The field that uniquely identifies a provisioned service on the panel.
- Whether the panel exposes proxy credentials/endpoints to surface in the client area.

## Testing (once wired)

1. Configure a product to use ProxyPanel, place a test order, pay the invoice.
2. Confirm `createServer` runs (log shows `Service provisioned`) and `proxypanel_service_id` is
   set on the service.
3. Exercise suspend/unsuspend/terminate/upgrade from the admin service page and confirm panel
   state changes. Re-run each to confirm idempotency (no duplicate side effects).
