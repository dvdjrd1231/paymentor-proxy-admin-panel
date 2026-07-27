# ProxyPanel Provisioning Module

Native Paymenter **Server** (provisioning) module for the IPv6 proxy admin panel
(melodyproxy) — the native rewrite of the legacy WHMCS *proxyPanel* module, wired to
the **live panel API**.

- **Location:** `extensions/Servers/ProxyPanel/`
- **Type:** Server (provisioning)
- **Status:** ✅ wired to the real API (create / suspend / unsuspend / terminate /
  renew / status / rotate / reboot / credentials).

## API

- **Base URL:** `https://admpx.melodyproxy.com/v0/services` (set in module settings).
- **Auth:** header `Panel: <token>` — stored as an **encrypted** module setting
  (never hard-coded, per spec item 12).

Endpoints used (verified against the original module's API client):

| Action | Method / path |
|---|---|
| Create | `POST /newIpv6` `{client_id, plan_tag, location_name, amount, authenticate:{username,password}, bwlimit}` |
| Suspend | `GET /stop/{id}` |
| Unsuspend | `GET /start/{id}` |
| Terminate | `GET /cancel/{id}` |
| Renew / extend | `GET /renew/{id}` · `GET /extend/{id}/{unixtimestamp}` |
| Status | `GET /{id}` |
| Plans | `GET /plans` · Locations `GET /locations` |
| Credentials | `POST /credentials/{id}` `{username,password}` |
| Rotate | `GET /rotate/{id}/1` · Set rotation `GET /setRotate/{id}/{minutes}` |
| Reboot | `GET /reboot/{id}` (+ `/hard`) |

Responses are JSON with `status` (`ok`/`error`) and `description`; create returns the
remote service `id` (+ `ips`).

## WHMCS → Paymenter mapping

| WHMCS | Paymenter | Notes |
|---|---|---|
| `CreateAccount` | `createServer` | idempotent — reuses existing remote id, just `/start` |
| `SuspendAccount` | `suspendServer` | `/stop` |
| `UnsuspendAccount` | `unsuspendServer` | `/start` |
| `TerminateAccount` | `terminateServer` | `/cancel`, idempotent |
| `ChangePassword` | `setCredentials()` | `/credentials` |
| Renewal | Service\Updated listener | Paymenter has no renew hook; when `expires_at` moves, the module calls `/extend/{id}/{ts}` |
| `ChangePackage` | `upgradeServer` | **panel does not allow package change** — surfaced as a clear error (matches the original module) |
| ClientArea (rotate/reboot) | `getActions` | Sync status, Rotate, Reboot |

## Configuration

### Module (admin → Servers → ProxyPanel)
- **Panel API URL** — e.g. `https://admpx.melodyproxy.com/v0/services`
- **Panel Token** — the `Panel:` header token (encrypted). Use **Test connection**
  (calls `/plans`) to verify.

### Product (admin → Product → this server)
- **Amount of proxies**, **Plan** (from `/plans`), **Location/Region** (from `/locations`),
  **Bandwidth limit**, **Max authorized IPs**.

## Provisioning data stored per service

- `proxypanel_service_id` — remote id (used by all later calls)
- `proxy_username`, `proxy_password` — generated proxy credentials (surface to the
  customer in the service view; can be changed via `setCredentials()`)

## Robustness (spec item 8)

- **Idempotency** — per-service/per-action lock (`proxypanel_lock`, 5-min TTL);
  create short-circuits if a remote id already exists; terminate is a no-op if never
  provisioned.
- **Retry** — transient HTTP failures retried (3×, 200 ms backoff).
- **Queue support** — provisioning runs on Paymenter's queue worker; long calls retry
  via Laravel's queue backoff.
- **Logging / error handling** — every op + failure logged as `[ProxyPanel]`; panel
  `status:error` responses raise a descriptive exception.
- **No hard-coded secrets** — API URL + token are settings; the token is encrypted.

## Enable

```
php artisan app:extension:enable Servers/ProxyPanel
```
Then create a Server with the URL + token, and attach it to your proxy products.

## Notes / differences from the old WHMCS module

- The panel's `/newIpv6` create endpoint is used (IPv6 proxy service), matching the live
  deployment. `client_id` is the Paymenter service id.
- Package change is intentionally unsupported (panel limitation) — to change plan, cancel
  and re-order.
- Rotation-limit / auth-IP management UIs from the WHMCS client area can be added as
  additional `getActions` views on request; the core lifecycle + status/rotate/reboot are
  wired.
