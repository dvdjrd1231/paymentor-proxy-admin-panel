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

### Verified against the live panel

Checked read-only against the client's dev panel. What it actually returns:

| Call | Real response |
|---|---|
| `GET /plans` | a **flat JSON array of strings** — `["1GB-Squid-HT","1GP-3Proxy-S5", …]`, no `{status,data}` envelope |
| `GET /locations` | a **flat array of `"Country - City"` strings** — `["Djibouti - Djibouti","Indonesia - Jakarta"]`. No tags and **no stock information** |
| `GET /{id}` (unknown) | `{"status":"error","description":"Unable to find service_id 0"}` |
| any call, bad/missing token | **HTTP 200** with the plain-text body `Unable to authorize your request` |

Two consequences the mock could never have surfaced:

1. **`location_name` is the literal `"Country - City"` string**, not a slug — which is why
   the WHMCS module passes `$params['configoptions']['Region']` straight through.
2. **An auth failure is HTTP 200 and not JSON.** Treating that as success would make a bad
   token look like a working one, and GET verbs such as `/stop` or `/cancel` would appear
   to succeed while doing nothing. `request()` now rejects any non-JSON body and reports
   the token as invalid.

### Live lifecycle test (service 1146, created and cancelled)

One real service was provisioned on the dev panel and removed again. Results:

| Call | Result |
|---|---|
| `POST /newIpv6` | ✅ `{"status":"ok","id":1146,…}` — created |
| `GET /{id}` | ✅ works |
| `POST /auth_ips/{id}` | ⚠️ `{"status":"error","description":"Unable to update this service (2)"}` |
| `GET /rotate/{id}/1` | ⚠️ `{"status":"error","description":"This node is not configured."}` |
| `GET /cancel/{id}` | ✅ `{"status":"ok","description":"Service is canceled and/or expired"}` |
| **`GET /stop/{id}`** | ❌ **HTTP 500, HTML error page** |
| **`GET /start/{id}`** | ❌ **HTTP 500, HTML error page** |
| **`GET /extend/{id}/{ts}`** | ❌ **HTTP 500, HTML error page** |
| **`GET /renew/{id}`** | ❌ **HTTP 404 — endpoint does not exist** |

The two `error` responses are expected for a service whose node is not yet configured.
The four failures are not: **suspend, unsuspend, renew and set-expiry cannot work against
this panel as it stands.** Those are exactly the calls billing automation depends on —
overdue suspension, reactivation on payment, and renewal.

This is a panel-side issue, not a module one: the module reports them as errors rather
than pretending they worked. **Open question for the client:** are `/stop`, `/start`,
`/extend` and `/renew` available on production, or under different names?

**Protocol selection (scope §8) is the plan tag.** `-HT` plans are HTTP, `-S5` are SOCKS5
(`1GP-3Proxy-HT` vs `1GP-3Proxy-S5`), so the customer chooses protocol by choosing a plan.
No separate field is required or supported.

### Stock per region

Inventory is the panel's to know, so availability is read from `GET /locations` rather than
Paymenter's manual **Stock** field. A region the panel reports as unavailable is shown but
labelled `(Out of stock)` — visible, not hidden, so the customer can see it exists and pick
another. This matches the client's WHMCS order form.

The panel's field name for this is not documented, so these are accepted, in order:

| Kind | Fields |
|---|---|
| Boolean | `available`, `in_stock`, `has_stock`, `enabled` |
| Count | `stock`, `free`, `available_count`, `remaining`, `free_proxies` |
| Inverted | `out_of_stock` |

Anything unrecognised is treated as **available** — failing open, so a sellable region is
never hidden by a naming mismatch.

> **Currently inert.** The live `GET /locations` returns bare strings with no stock field
> at all, so nothing is ever marked out of stock. The client's WHMCS order form does show
> "(Out of stock)" per region, so that information exists somewhere — but not on this
> endpoint. **Open question:** which call exposes per-region availability?

### Country flags on regions

With **Show country flags on regions** enabled (default), each option is prefixed with its
country flag: `🇺🇸  United States - Kansas City`. The country is taken from the text before
the first dash and matched against Paymenter's own ISO-3166 list
(`config('app.countries')`), plus a small alias table for spellings like `USA`, `UK` and
`UAE`. An unrecognised country is left untouched — the label is never lost or guessed at.

Only the **label** changes; the value sent to the panel as `location_name` is still the
bare `server_tag`.

> **Windows shows letters, not flags.** Flags in a `<select>` can only be Unicode
> regional-indicator characters — HTML options cannot contain images. Windows ships no
> flag glyphs, so Chrome, Edge and Firefox on Windows render `🇺🇸` as `US`. macOS, iOS,
> Android and most Linux desktops show the flag. To get real flag images on Windows the
> native `<select>` would have to be replaced with a custom JavaScript dropdown; turn the
> setting off if the letter fallback is undesirable.

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

## Activation: the service waits for the panel

`POST /newIpv6` returning `ok` means the panel **accepted** the request, not that the
proxies exist — the panel lists a new service as `status: pending, deployed: none` with an
empty `ips` array until it finishes. The WHMCS module handles this by setting the service
`Pending` after create (`'domainstatus' => 'Pending'`) and letting `callback.php` mark it
Active when the panel calls back.

This module does the same:

1. `createServer()` requests provisioning and leaves the service **pending**.
2. The panel calls back (or an admin presses **Sync status**).
3. Only then does the service become **active**, recorded in `proxy_confirmed_at`.

Because Paymenter activates services in `RenewServiceService` independently of the
provisioning job, a `Service\Updated` listener reverts any activation that is not backed by
a confirmation — which covers the job running before *or* after core sets the status.

A service therefore never shows as active to the customer while the panel still has it
queued. `syncStatus()` is the manual fallback if a callback is lost: it activates the
service once the panel reports proxies assigned.

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
