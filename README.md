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
| 2 | Client & Admin area customization | Custom theme + Filament panel config | 🔴 planned |
| 3 | Support ticket system (WHMCS-like) | Core tickets + `Others/*` extensions | 🔴 planned |
| 4 | Payment gateways: Stripe, Cryptomus (config) | Existing `Gateways/Stripe`, community Cryptomus | 🟡 config/docs |
| 4 | Payment gateways: CoinPayments, Binance (dev) | New `Gateways/CoinPayments`, `Gateways/Binance` | 🟢 CoinPayments built |
| 5 | Country-based gateway availability | `Others/GatewayRules` (event-filtered) | 🔴 planned |
| 6 | Payment method fees | `Others/PaymentFees` | 🔴 planned |
| 7 | Generic service provisioning lifecycle | `Servers/*` contract (native) | ✅ native |
| 8 | proxyPanel module conversion | New `Servers/ProxyPanel` | 🟡 scaffolded |
| 9 | Brazilian customer registration (CPF/CNPJ) | `Others/BrazilianRegistration` | 🟢 built |
| 10 | Disable domain sales | Config + policy override (upgrade-safe) | 🔴 planned |
| 11 | Notification system (Email + Telegram) | `Others/Notifications` + queue | 🔴 planned |
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
├── File/                         # Original client brief (context; not part of runtime)
└── custom/                       # Non-runtime authored assets (specs, mockups)
```

Everything authored by this project is listed in [`docs/AUTHORED-FILES.md`](docs/AUTHORED-FILES.md).

---

## Quick start (local development)

> Local dev requires PHP 8.3+. See [`docs/01-installation.md`](docs/01-installation.md) for the
> production Debian 13 procedure.

```bash
cp .env.example .env
# edit DB_* (or switch to sqlite for a quick spin — see docs)
composer install
npm install && npm run build
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

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
- [`docs/12-security.md`](docs/12-security.md) — security practices & checklist

Each custom module also ships a `README.md` inside its own extension folder.
