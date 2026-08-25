# Paymenter Commercial Platform — IPv6 Proxy Provisioning

A customized, commercial deployment of [Paymenter](https://paymenter.org) that replaces WHMCS
for an IPv6 proxy service business. It provides the store, billing, invoicing, support
ticketing, customer management, and **automated service provisioning** against an external
proxy admin panel via API.

This repository vendors the full Paymenter application (Laravel 12 / Filament 5 / Livewire 4)
and layers **custom, upgrade-safe extensions, a theme, deployment automation, and
documentation** on top of it. Wherever possible we follow Paymenter's native extension
architecture and **do not modify core files**, so future upstream updates remain applyable.

> Base upstream version: Paymenter `master` (see `README.paymenter-upstream.md`).
> Requires **PHP 8.3+** (8.4 recommended), MySQL/MariaDB, Redis, Node 20+.

---

## What this project delivers (spec → status)

| # | Requirement | Native mechanism | Status |
|---|-------------|------------------|--------|
| 1 | Deployment automation (Debian 13) | `scripts/install-debian13.sh` + docs | 🟡 scaffolded |
| 2 | Client & Admin area customization | `themes/proxy` (WHMCS-style, full journey) + `Others/AdminOps` metrics + settings branding | 🟢 built |
| 3 | Support ticket system (WHMCS-like) | Core tickets + `Others/TicketTools` (quick replies, internal notes) | 🟢 built |
| 4 | Payment gateways: Stripe, Cryptomus (config) | Core `Gateways/Stripe` + first-party `Gateways/Cryptomus` | 🟢 built |
| 4 | Payment gateways: CoinPayments, Binance (dev) | New `Gateways/CoinPayments`, `Gateways/Binance` | 🟢 both built |
| 5 | Country-based gateway availability | `Others/GatewayRules` (server-side rules) | 🟢 built |
| 6 | Payment method fees | `Others/PaymentFees` (server-side rules) | 🟢 built |
| 7 | Generic service provisioning lifecycle | `Servers/*` contract (native) | ✅ native |
| 8 | proxyPanel module conversion | New `Servers/ProxyPanel` (lifecycle + callback endpoint + client-area details) | 🟢 built |
| 8b | Failed provisioning visible + retryable | New `Others/ProvisioningOps` (admin list + Retry, status protection) | 🟢 built |
| 9 | Brazilian customer registration (CPF/CNPJ) | `Others/BrazilianRegistration` | 🟢 built |
| 10 | Disable domain sales | N/A by architecture — no domain system exists (documented) | 🟢 done |
| 11 | Notification system (Email + Telegram) | Core email/in-app + `Others/Notifications` Telegram, queued | 🟢 built |
| 12 | Security hardening | Cross-cutting (see `docs/12-security.md`) | 🟡 ongoing |

Legend: ✅ provided by core · 🟢 done · 🟡 in progress / scaffolded · 🔴 planned

See [`docs/00-project-plan.md`](docs/00-project-plan.md) for the full phased plan and the
design decision behind each item.

---

## Repository layout

```
.
├── app/ bootstrap/ config/ …    # Vendored Paymenter core (do NOT edit unless documented)
├── extensions/
│   ├── Gateways/
│   │   ├── Stripe/  Mollie/ …    # Upstream gateways
│   │   └── CoinPayments/         # ← custom (this project)
│   ├── Servers/
│   │   ├── Pterodactyl/ …        # Upstream servers
│   │   └── ProxyPanel/           # ← custom: IPv6 proxy provisioning
│   └── Others/                   # ← custom sub-apps (fees, rules, notifications, BR reg.)
├── themes/                       # ← custom client-area theme(s)
├── scripts/                      # Deployment / backup / maintenance automation
│   └── install-debian13.sh
├── docs/                         # Delivery documentation (install, modules, ops)
│   └── client-brief/             # Original brief + panel API docs (context, not runtime)
└── custom/                       # Non-runtime authored assets (specs, mockups)
```

Everything authored by this project is listed in [`docs/AUTHORED-FILES.md`](docs/AUTHORED-FILES.md).

---

## Quick start (local development)

> Local dev requires PHP 8.3+. See [`docs/01-installation.md`](docs/01-installation.md) for the
> production Debian 13 procedure.

The sequence below is the one actually executed end-to-end on a clean checkout
(PHP 8.4.24, SQLite) — every step is verified, not assumed.

```bash
cp .env.example .env
# For a quick local spin, set: DB_CONNECTION=sqlite, DB_DATABASE=database/database.sqlite,
# CACHE_STORE=file, QUEUE_CONNECTION=sync, SESSION_DRIVER=file  (avoids needing Redis/MariaDB)
touch database/database.sqlite

composer install
npm install && npm run build

php artisan key:generate
php artisan migrate --seed          # seeds settings, the admin role, USD and 12 notification templates
php artisan storage:link

# Create the first admin (role 1 = admin, seeded by DatabaseSeeder)
php artisan app:user:create Admin Local admin@example.com 'CHANGE-ME' 1

# Paymenter reads its canonical URL from the `app_url` DB setting, NOT from .env APP_URL.
# If they disagree every request is redirected to the setting's host.
php artisan app:settings:change app_url "http://127.0.0.1:8000"

php artisan serve
```

Then, in the admin panel (`/admin`):

1. **Admin → Extensions** — enable the modules you need (`CoinPayments`, `Binance`,
   `Cryptomus`, `ProxyPanel`, and the `Others/*` mini-apps). Enabling runs each
   extension's `installed()` hook, which applies its migrations.
2. **Admin → Settings** — set **Theme** to `proxy` for the WHMCS-style client area.

## Production deployment

```bash
sudo bash scripts/install-debian13.sh
```

Provisions PHP 8.4, MariaDB, Redis, Nginx + HTTPS (Let's Encrypt), the queue worker, the
scheduler, and automated backups. Full walkthrough: [`docs/01-installation.md`](docs/01-installation.md).

---

## Documentation index

- [`docs/00-project-plan.md`](docs/00-project-plan.md) — scope, phases, decisions
- [`docs/01-installation.md`](docs/01-installation.md) — Debian 13 install, HTTPS, queues, backups
- [`docs/02-updates.md`](docs/02-updates.md) — updating core without losing customizations
- [`docs/modules/coinpayments.md`](docs/modules/coinpayments.md) — CoinPayments gateway
- [`docs/modules/proxypanel.md`](docs/modules/proxypanel.md) — proxyPanel provisioning module
- [`docs/modules/provisioning-ops.md`](docs/modules/provisioning-ops.md) — provisioning failure log + retry
- [`docs/VERIFICATION.md`](docs/VERIFICATION.md) — **what has actually been tested, and what has not**
- [`docs/12-security.md`](docs/12-security.md) — security practices & checklist

Each custom module also ships a `README.md` inside its own extension folder.
