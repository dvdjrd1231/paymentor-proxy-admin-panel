# ProxyPanel Provisioning Module

Native Paymenter **Server** (provisioning) module for the IPv6/IPv4 proxy admin panel —
the native rewrite of the legacy WHMCS *proxyPanel* module, implemented against the
panel's documented `api.md` (RotatingServices).

- **Location:** `extensions/Servers/ProxyPanel/`
- **Type:** Server (provisioning)
- **Status:** all `api.md` endpoints implemented; verified end-to-end (32 checks) against
  `scripts/mock-proxy-panel.php`.

## API

Scope §8 is *"convert the existing WHMCS proxyPanel module"*, so the endpoints this module
calls are the ones that module calls in production against
`https://admpx.melodyproxy.com/v0/services`.

Several are **not written up in `api.md`** — `/plans`, `/locations`, `/stop`, `/start`,
`/credentials`, `/auth_ips`, `/rotate`, `/setRotate` — but the working module calls them,
so the module is treated as authoritative and `api.md` as an incomplete description.

- **Base URL:** configurable, e.g. `https://<panel-host>/v0/services`
- **Auth:** header `Panel: <token>` — an **encrypted** module setting

| Action | Method / path |
|---|---|
| Service info | `GET /{id}` → `id, expiration, plan_manual_rotate, plan_change_rotate, plan_max_rotate, rotation_counter, ips[{ip,port}]` |
| Create | `POST /newIpv6` `{client_id, plan_tag, location_name, amount, authenticate{username,password}, bwlimit}` |
| Suspend / unsuspend | `GET /stop/{id}` · `GET /start/{id}` |
| Renew | `GET /renew/{id}` (clears rotation counters) |
| Set expiration | `GET /extend/{id}/{unixtimestamp}` |
| Upgrade / downgrade | `GET /expand/{id}/{amount}` · `GET /shrink/{id}/{amount}` |
| Terminate | `GET /cancel/{id}` |
| Credentials | `POST /credentials/{id}` `{username, password}` |
| Authorized IPs | `POST /auth_ips/{id}` `{ips: []}` — max 3 |
| Manual rotation | `GET /rotate/{id}/1` |
| Rotation interval | `GET /setRotate/{id}/{minutes}` |
| Reboot | `GET /reboot/{id}[/hard]` |
| Catalogue | `GET /plans` · `GET /locations` — populate the Plan and Region dropdowns |
| Blacklist | `GET /blacklist/{blacklist_id}/{enabled\|disabled}` |

All responses are JSON with a `status` field (`ok`/`error`) and an optional `description`.

`client_id` is the **Paymenter service id**, matching the WHMCS module — its source says
so explicitly (`// clientid === $params['serviceid']`), and its callback looks the service
up by that same id. `api.md` describes `client_id` as a user id; the working code wins.

### Protocol

Scope §8 lists "Protocol selection". Neither the WHMCS module nor the panel API has any
protocol field — zero occurrences in either. It is therefore offered as a **product
option** (HTTP / HTTPS / SOCKS5), recorded on the service and shown to the customer, but
**not sent upstream**. If the panel does support protocol selection, tell us the field and
it becomes a one-line change.

## WHMCS → Paymenter mapping

| WHMCS | Paymenter | Notes |
|---|---|---|
| `CreateAccount` | `createServer` | `POST /newIpv6`; idempotent — an existing remote id short-circuits, so a duplicate InvoicePaid never creates two services |
| `SuspendAccount` | `suspendServer` | `GET /stop/{id}` |
| `UnsuspendAccount` | `unsuspendServer` | `GET /start/{id}` |
| `TerminateAccount` | `terminateServer` | `GET /cancel/{id}`, idempotent |
| `ChangePackage` | `upgradeServer` | `GET /expand` or `/shrink` by the delta — the panel grows/shrinks in place |
| `ChangePassword` | `changePassword()` | `POST /credentials/{id}` |
| Renewal | `Service\Updated` listener | Paymenter has no renew hook; on `expires_at` change the module calls `GET /renew/{id}` then `GET /extend/{id}/{ts}` |
| `ClientArea` | `getActions()` + module methods | proxies list, export, manual rotate, rotation interval, auth IPs, password, API key, reboot |

## Configuration

### Module (admin → Servers → ProxyPanel)
- **Panel API URL** — e.g. `https://<panel-host>/v0/services`
- **Panel Token** — the `Panel:` header token (encrypted).
- **Callback Secret** — shared secret the panel must present when calling back into
  Paymenter (encrypted). Leave blank to disable the callback endpoint entirely.

### Product (admin → Product → this server)

Mirrors the WHMCS module's `ConfigOptions`: **Amount proxies**, **Plan** (dropdown from
`/plans`), **Protocol**, **Allow manual Rotation**, **Allow change rotation time**,
**How many auth_ips can be allowed**, **How many rotations per period are allowed**,
**Bandwidth limit**.

**Region** is a *checkout* option (dropdown from `/locations`, labelled `Country - City`)
so one product can be sold in several locations — exactly as the WHMCS order form did.

## Provisioning data stored per service

- `proxypanel_service_id` — the panel's service id (used by every later call)
- `proxy_username`, `proxy_password` — proxy credentials (changeable via `POST /aa`)
- `proxy_api_key` — the panel-issued API key shown to the customer
- `proxy_amount`, `proxy_auth_ips`, `proxy_rotation_time`
- `proxy_ips` — `ip:port` endpoints, comma separated
- `proxy_expiration`, `proxy_rotation_counter`, `proxy_max_rotate`, `proxy_synced_at` —
  cached panel state, refreshed by `createServer()`, **Sync status**, and the callback

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
| `proxy_count` | Proxies |
| `auth_ips` | Authorized IPs |
| `rotation_time` | Rotation interval (minutes) |
| `rotations_used` | Rotations used |
| `api_key` | API key |
| `panel_expiration` | Expires on panel |
| `panel_service_id` | Service reference |
| `last_synced` | Last updated |

**Export format** — `exportProxies()` returns one line per proxy as
`host:port:username:password`. IPv6 hosts are bracketed
(`[2a01:4f8::1]:10000:user:pass`) because a bare IPv6 address contains colons and would
otherwise be unparseable.

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
| `ok`, `success`, `active`, `running`, `started`, `online`, `created`, `unsuspended` | `active` |
| `suspended`, `stopped`, `paused`, `offline`, `expired` | `suspended` |
| `cancelled`, `canceled`, `terminated`, `deleted`, `destroyed` | `cancelled` |
| `error`, `failed`, `failure` | **not activated** — recorded as a provisioning failure with the panel's `description`, mirroring WHMCS's `AfterModuleCreateFailed` |

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

- `client_id` on `POST /newIpv6` is the Paymenter service id, so the panel can identify the
  service in its callbacks.
- **Package change is supported**, unlike the old module: the panel grows and shrinks a
  service in place, so an upgrade is `expand`/`shrink` by the difference in proxy count
  rather than a cancel-and-reorder.
- **Region** is a checkout option (`Country - City`), not a product setting, so one
  product can be sold in several locations — matching the WHMCS order form.
- Rotation is gated client-side by the plan's `plan_max_rotate` / `rotation_counter`
  pulled from the panel, so the customer gets a clear message rather than a panel error.

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
