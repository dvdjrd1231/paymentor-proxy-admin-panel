# Handover

Everything needed to install, configure and take over this build — plus the short list of
things still waiting on you.

Read [`VERIFICATION.md`](VERIFICATION.md) alongside this: it states exactly what has been
tested and what has not.

---

## 1. Install

### Production (Debian 13)

```bash
sudo bash scripts/install-debian13.sh
```

Full walkthrough: [`01-installation.md`](01-installation.md).

### Local / staging

Verified end-to-end on a clean checkout (PHP 8.4.24, SQLite):

```bash
cp .env.example .env
# quick local spin: DB_CONNECTION=sqlite, DB_DATABASE=database/database.sqlite,
#                   CACHE_STORE=file, QUEUE_CONNECTION=sync, SESSION_DRIVER=file
touch database/database.sqlite

composer install
npm install && npm run build

php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan app:user:create Admin Local admin@example.com 'CHANGE-ME' 1
php artisan app:settings:change app_url "http://127.0.0.1:8000"
php artisan serve
```

Then in `/admin`:

1. **Admin → Extensions** — enable the modules you want. Enabling runs each extension's
   `installed()` hook, which applies its migrations.
2. **Admin → Settings** — set **Theme** to `proxy`.

### Three things that will bite you if you don't know them

1. **`app_url` is a database setting, not `.env APP_URL`.** If they disagree, every request
   redirects to the setting's host. Change it with `php artisan app:settings:change app_url "<url>"`.
2. **There is no `php artisan app:extension:enable`.** Enable extensions in the admin panel.
   The CLI only has `app:extension:install {type} {name}` (runs the install hook for an
   already-registered extension), `disable`, `create`, `upgrade`.
3. **Requires PHP 8.3+** (8.4 recommended). PHP 8.2 will not install the dependencies.

---

## 2. Module configuration

### ProxyPanel — provisioning (`extensions/Servers/ProxyPanel`)

**Admin → Extensions** → enable, then **create a Server** and set:

| Field | Notes |
|---|---|
| **Panel API URL** | e.g. `https://admpx.melodyproxy.com/v0/services` |
| **Panel Token** | sent as the `Panel:` header. Encrypted at rest. |
| **Callback Secret** | shared secret the panel must present when calling back. Encrypted. **Leave blank to disable the callback endpoint.** |

Per product (**Admin → Product → this server**): **Amount of proxies**, **Plan**,
**Location/Region**, **Bandwidth limit**, **Max authorized IPs**. Plan and Location are
populated live from the panel's `/plans` and `/locations`.

Panel callback URL to give the panel:

```
POST https://YOUR-DOMAIN/extensions/proxypanel/callback
     X-Panel-Secret: <callback secret>
     — or —
     X-Panel-Signature: <hex HMAC-SHA256 of the raw body, keyed with the secret>
```

Full contract: [`modules/proxypanel.md`](modules/proxypanel.md).

### CoinPayments (`extensions/Gateways/CoinPayments`)

| Field | Where to get it |
|---|---|
| **Merchant ID** | Account Settings → Merchant Settings |
| **API Public Key** | My Account → API Keys (needs `create_transaction`) |
| **API Private Key** | shown once when the key is created |
| **IPN Secret** | Account Settings → Merchant Settings |
| **Receive Currency** | coin you want to receive, e.g. `BTC`, `USDT.TRC20`, or `LTCT` for testnet |
| **Test Mode** | on = testnet. Must be paired with `LTCT`, or the module refuses to run. |

IPN URL: `https://YOUR-DOMAIN/extensions/coinpayments/ipn`

### Binance Pay (`extensions/Gateways/Binance`)

| Field | Notes |
|---|---|
| **API Key (Certificate SN)** | Binance Merchant dashboard. Encrypted. |
| **API Secret** | Encrypted; signs every request. |
| **Order Currency** | e.g. `USDT`, `BUSD`, `EUR` |
| **Test Mode** | routes calls to the sandbox host below |
| **Sandbox API URL** | only used when Test Mode is on; Binance issues this per merchant |

Webhook URL: `https://YOUR-DOMAIN/extensions/binance/webhook`

### Cryptomus (`extensions/Gateways/Cryptomus`)

**Merchant UUID** + **Payment API Key**. Callback:
`https://YOUR-DOMAIN/extensions/cryptomus/webhook`

### Supporting modules

| Module | Admin screen | Purpose |
|---|---|---|
| `Others/ProvisioningOps` | **Services → Provisioning** | failed provisioning + **Retry** |
| `Others/GatewayRules` | **Gateway Rules** | which gateways show, by country/currency/amount/product |
| `Others/PaymentFees` | **Payment Fee Rules** | per-gateway fixed/percentage fees |
| `Others/TicketTools` | **Canned Responses**, **Ticket Notes** | quick replies + internal notes |
| `Others/AdminOps` | admin dashboard | operations metrics widget |
| `Others/Notifications` | extension settings | Telegram notifications |
| `Others/BrazilianRegistration` | registration form | CPF/CNPJ fields + validation |

---

## 3. Editing the wording

All client-area text lives in translation files — no text is buried in logic:

| File | Covers |
|---|---|
| `lang/en/theme.php` | everything the proxy theme adds (registration labels, login, breadcrumbs) |
| `lang/en/proxypanel.php` | the proxy detail labels on a customer's service page |
| `lang/en/*.php` | Paymenter's own wording (invoices, services, tickets, products, auth) |

Homepage copy is a theme setting: **Admin → Settings → Theme → Home Page Text**.

To translate, copy the file to `lang/<locale>/`.

---

## 4. Testing without live credentials

`scripts/mock-proxy-panel.php` is a test double for the proxy panel, with fault injection:

```bash
php -S 127.0.0.1:9000 scripts/mock-proxy-panel.php
# Panel API URL: http://127.0.0.1:9000/v0/services   Panel Token: test-token

curl "http://127.0.0.1:9000/_control/fail?on=1"   # break the panel on purpose
curl "http://127.0.0.1:9000/_control/state"       # what it thinks is provisioned
curl "http://127.0.0.1:9000/_control/reset"
```

With it in failure mode you can confirm the important safety property yourself: order a
service, watch it appear in **Services → Provisioning** as failed, confirm the service is
`pending` rather than `active`, heal the panel, press **Retry**.

---

## 5. Open questions — I need these from you

### A. Proxy panel API

The outbound calls are mapped from your old WHMCS module and work. What is undocumented is
what the panel sends **back**:

1. **Does the panel send callbacks at all?** If yes: what URL/method, is the callback URL
   configurable, and how does it authenticate (shared secret header, HMAC signature, IP
   allow-list)?
2. **What does the callback body look like** — which field identifies the service, and what
   are the exact state strings?
3. **`GET /{id}` response shape** — we currently read `status`/`state`,
   `ips`/`proxies`/`ipv6`, `host`/`hostname`/`gateway`. Which are the real ones?
4. **Bandwidth / authorized IPs** — confirm the `bwlimit` field name, and whether authorized
   IPs are set on create or via a separate endpoint.
5. **Rotation** — what does the trailing `1` in `GET /rotate/{id}/1` mean? Should
   `setRotate/{id}/{minutes}` be exposed to customers?
6. **Credentials** — does `POST /credentials/{id}` take `{username, password}`, and does
   changing them drop active sessions?

Until these are answered the callback endpoint stays deliberately tolerant about field
names and refuses to apply any state it does not recognise.

### B. Module 4

Still your call. The two gateways are built to a shared shape — config → `pay()` →
signed webhook → `ExtensionHelper::addPayment()` keyed on the gateway transaction id — so
adding a fourth is hours, not days. Tell me the gateway and I'll add it.

### C. Sandbox credentials

To finish the end-to-end payment tests I need:

- a **Binance Pay sandbox** merchant account (API key, secret, and the sandbox host they
  issue you), and
- a **CoinPayments** account with an `LTCT` testnet wallet.

### D. Client-area reference screenshot

Send the reference you mentioned. Every page is already built with the compact WHMCS-style
layout and a small CSS design system (`themes/proxy/views/layouts/whmcs-css.blade.php`), so
matching your reference is a styling pass — colours, spacing, header — not a rebuild.

### E. Repository location

The git remote is currently a personal repository. Your brief says this code must live in
your org (`7hooptelecom`) and stay private. Create the repo(s) and I'll push there — tell
me whether you want the single repo as it is now, or split into the four repos your brief
names (core, theme, proxy-panel, binance, coinpayments).
