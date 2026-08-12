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
- **Callback Secret** — shared secret the panel must present when calling back into
  Paymenter (encrypted). Leave blank to disable the callback endpoint entirely.

### Product (admin → Product → this server)
- **Amount of proxies**, **Plan** (from `/plans`), **Location/Region** (from `/locations`),
  **Bandwidth limit**, **Max authorized IPs**.

## Provisioning data stored per service

- `proxypanel_service_id` — remote id (used by all later calls)
- `proxy_username`, `proxy_password` — generated proxy credentials (can be changed via
  `setCredentials()`)
- `proxy_ips`, `proxy_host`, `proxy_status`, `proxy_synced_at` — cached panel state,
  written by `createServer()`, by **Sync status**, and by the panel callback

## What the customer sees

`getActions()` returns `type => 'text'` entries, which Paymenter renders as labelled
fields on the service page, plus the Sync / Rotate / Reboot buttons. The fields are read
from the cached properties above, so opening the page never blocks on the panel API.

Labels come from `lang/en/proxypanel.php` — reword them there, no code change needed:

| Key | Default |
|---|---|
| `proxy_username` | Proxy username |
| `proxy_password` | Proxy password |
| `proxy_endpoints` | Proxy addresses |
| `proxy_host` | Proxy host |
| `panel_status` | Status |
| `panel_service_id` | Service reference |
| `last_synced` | Last updated |

## Panel callback (panel → Paymenter)

```
POST https://YOUR-DOMAIN/extensions/proxypanel/callback
```

Route name `extensions.servers.proxypanel.callback`, CSRF-exempt.

**Authentication** — set **Callback Secret** in the module settings (encrypted). The panel
must send *either*:

- `X-Panel-Secret: <callback secret>`, or
- `X-Panel-Signature: <hex HMAC-SHA256 of the raw request body, keyed with the secret>`

Both are compared in constant time. If no secret is configured the endpoint returns
**403** — callbacks are opt-in, never open by default. A bad secret/signature returns
**401**.

**Body** (JSON). The service is resolved by whichever identifier the panel sends:

| Field | Meaning |
|---|---|
| `service_id` or `client_id` | the Paymenter service id (what we send as `client_id` on create) |
| `id` or `panel_id` | the panel's own service id (`proxypanel_service_id`) |
| `status` or `event` | the new state |

Recognised states → Paymenter status:

| Panel state | Becomes |
|---|---|
| `active`, `running`, `started`, `online`, `created`, `unsuspended` | `active` |
| `suspended`, `stopped`, `paused`, `offline` | `suspended` |
| `cancelled`, `canceled`, `terminated`, `deleted`, `destroyed` | `cancelled` |

Anything else is **logged and recorded as a `callback` row in Services → Provisioning**,
and **not** applied — an unknown state never silently changes a customer's service. Any
`ips` / `host` in the body are cached onto the service either way.

Re-delivering the same callback is a no-op (the status is only written when it differs),
so the panel can safely retry.

> **Open question for the client:** the outbound API is mapped from the original WHMCS
> module, but nothing documents what the panel sends *back*. This endpoint is deliberately
> tolerant about field names. Once the real callback format is known, confirm the field
> names and state values above — see § Open questions.

## Robustness (spec item 8)

- **Idempotency** — per-service/per-action lock (`proxypanel_lock`, 5-min TTL);
  create short-circuits if a remote id already exists; terminate is a no-op if never
  provisioned.
- **Retry** — transient HTTP failures retried (3×, 200 ms backoff).
- **Queue support** — provisioning runs on Paymenter's queue worker; long calls retry
  via Laravel's queue backoff.
- **Logging / error handling** — every op + failure logged as `[ProxyPanel]`; panel
  `status:error` responses raise a descriptive exception.
- **Admin-visible failures + retry** — every lifecycle failure is recorded through
  `Others/ProvisioningOps` and appears under **Services → Provisioning** with a Retry
  button. A failed *create* also reverts the service from `active` back to `pending`, so a
  provisioning failure never leaves an order silently active. See
  [`provisioning-ops.md`](provisioning-ops.md).
- **No hard-coded secrets** — API URL + token + callback secret are settings, all encrypted.

## Testing without the real panel

`scripts/mock-proxy-panel.php` is a drop-in test double implementing this exact contract,
including fault injection:

```bash
php -S 127.0.0.1:9000 scripts/mock-proxy-panel.php
# Panel API URL: http://127.0.0.1:9000/v0/services      Panel Token: test-token

curl "http://127.0.0.1:9000/_control/fail?on=1"   # every call now returns HTTP 500
curl "http://127.0.0.1:9000/_control/state"       # inspect provisioned services
curl "http://127.0.0.1:9000/_control/reset"       # wipe state
```

## Enable

Enable **ProxyPanel** under **Admin → Extensions**. Then create a Server with the URL +
token, and attach it to your proxy products.

> There is no `app:extension:enable` artisan command. The CLI equivalent of only the
> install hook (for an extension already registered in the database) is
> `php artisan app:extension:install server ProxyPanel`.

## Notes / differences from the old WHMCS module

- The panel's `/newIpv6` create endpoint is used (IPv6 proxy service), matching the live
  deployment. `client_id` is the Paymenter service id.
- Package change is intentionally unsupported (panel limitation) — to change plan, cancel
  and re-order.
- Rotation-limit / auth-IP management UIs from the WHMCS client area can be added as
  additional `getActions` views on request; the core lifecycle + status/rotate/reboot are
  wired.

## Open questions for the client

These are the only things still unknown about the panel integration. Everything else is
built and verified against `scripts/mock-proxy-panel.php`.

1. **Does the panel send callbacks at all?** If yes:
   - what URL/method does it call, and can the callback URL be configured per-deployment?
   - how does it authenticate — a shared secret header, an HMAC signature, or an IP
     allow-list? (We accept `X-Panel-Secret` or `X-Panel-Signature` HMAC-SHA256.)
   - what does the JSON body look like — which field identifies the service, and what are
     the exact state strings?
2. **Status endpoint response shape** — `GET /{id}` currently reads `status`/`state`,
   `ips`/`proxies`/`ipv6`, and `host`/`hostname`/`gateway`. Confirm the real field names so
   the tolerant fallbacks can be tightened.
3. **Bandwidth / authorized-IP limits** — the product config collects `bwlimit` and
   `auth_ips`. `bwlimit` is sent on create; confirm the field name, and whether authorized
   IPs are set on create or through a separate endpoint.
4. **Rotation** — `GET /rotate/{id}/1`: confirm what the trailing `1` means, and whether
   `setRotate/{id}/{minutes}` should be exposed to customers.
5. **Credentials** — confirm `POST /credentials/{id}` accepts `{username, password}` and
   whether changing them interrupts active sessions.
