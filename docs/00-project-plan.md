# Project Plan — Paymenter Commercial Platform

This document maps every requirement in the client brief (`File/`) to a concrete,
**upgrade-safe** implementation approach using Paymenter's native architecture, and sequences
the work into phases.

## Guiding principles

1. **No core edits.** Everything lives in `extensions/` (native loader), `themes/`, config,
   or published/override points. Where a genuine core touch is unavoidable it is documented in
   `docs/CORE-TOUCHPOINTS.md` with a re-apply note for updates.
2. **Server-side enforcement.** All money math, gateway availability rules, and fee
   calculations run on the backend — never trusted from the client.
3. **Idempotency & queues.** Every provisioning action and payment webhook is idempotent and,
   where long-running, dispatched to Laravel queues with automatic retry.
4. **No secrets in code.** All credentials live in encrypted extension settings or `.env`.

## Paymenter extension model (ground truth)

Confirmed from the vendored source:

- **Gateways** — `extensions/Gateways/<Name>/<Name>.php` extends
  `App\Classes\Extension\Gateway`, annotated with `#[App\Attributes\ExtensionMeta(...)]`.
  Key methods: `getConfig()`, `pay($invoice, $total)`, `webhook(Request)`, optional billing
  agreements. Payments are recorded through `App\Helpers\ExtensionHelper::addPayment()`,
  `addProcessingPayment()`, `addFailedPayment()`, `addPaymentFee()`. Routes/views are
  registered from the extension's `boot()` + `routes.php`.
- **Servers (provisioning)** — `extensions/Servers/<Name>/<Name>.php` extends
  `App\Classes\Extension\Server`. Lifecycle methods, each called with
  `($service, $productSettings, $serviceProperties)`:
  `createServer`, `suspendServer`, `unsuspendServer`, `terminateServer`, `upgradeServer`,
  plus `getConfig()`, `testConfig()`, `getProductConfig($values)`, `getActions()`,
  `getView()`. `ExtensionHelper::hasFunction()` means we implement only what proxyPanel
  supports; unsupported actions are simply omitted.
- **Others** — `extensions/Others/<Name>/` are full mini-apps: `Models/`, `database/`
  migrations, `routes/`, `Livewire/`, `Admin/Resources/` (Filament), `Listeners/`,
  `Policies/`. This is where cross-cutting features live (fees, gateway rules, notifications,
  Brazilian registration).
- **Themes** — `themes/<name>/` with `theme.php`, `views/` (Blade), `css/js`, `vite.config.js`.
  Client area is fully overridable without editing core.
- **Admin area** — Filament 5 panel; extensions register `Admin/Resources/*` and pages.

## Requirement mapping

### 1. Deployment (Debian 13)
`scripts/install-debian13.sh` — idempotent bash installer: PHP 8.4 + extensions, Composer,
MariaDB, Redis, Nginx, Certbot/HTTPS, app clone + `.env`, `migrate --seed`, `storage:link`,
build assets, systemd units for `queue:work` and the scheduler, nightly backup cron.
Backup/restore scripts in `scripts/backup.sh` + `scripts/restore.sh`. Docs: `docs/01-…`.

### 2. UI customization (client + admin)
- Client area: fork `themes/default` → `themes/proxy` with a modern, responsive, WHMCS-like
  dashboard and simplified nav. Registered via `.env THEME=proxy`.
- Admin area: Filament panel customization (branding, navigation groups, dashboard widgets for
  operational metrics — open services, MRR, pending tickets, failed payments).

### 3. Support tickets (WHMCS-like)
Core ticketing exists. Add: departments, priorities, canned/quick replies, internal notes,
attachments, service association, permission-based access, auto-notifications. Implemented as
`Others/Tickets*` augmentations + notification hooks (item 11). Avoid core edits by using
events + Filament resource extension.

### 4. Payment gateways
- **Stripe** — ships in core (`Gateways/Stripe`). Task = configuration + docs.
- **Cryptomus** — community gateway; vendor + configure + docs.
- **CoinPayments** — ✅ built here: `Gateways/CoinPayments` (IPN, HMAC-SHA512 validation,
  idempotency, logging). See `docs/modules/coinpayments.md`.
- **Binance** — `Gateways/Binance` using Binance Pay official API (certificate/HMAC signed
  webhooks). Built after CoinPayments (same pattern).

All gateways: automatic processing, webhooks, signature validation, idempotency, logging,
error handling, duplicate-payment protection (enforced via `ExtensionHelper` transaction
lookups keyed on gateway transaction id).

### 5. Country-based gateway availability
`Others/GatewayRules` — a rule table (country, product, product group, customer type,
currency, invoice amount range → allowed/denied gateways) evaluated by listening to the
checkout gateway-list build (`ExtensionHelper::getCheckoutGateways`). Enforced server-side.

### 6. Payment method fees
`Others/PaymentFees` — per-gateway fee rules: fixed, percentage, or fixed+percentage, scoped
by country/currency/product/customer. Applied server-side when an invoice's payment gateway is
chosen; recorded as an invoice line + via `addPaymentFee`.

### 7. Service provisioning (generic)
Native Server contract already covers create/activate/suspend/unsuspend/cancel/renew/
upgrade/downgrade/sync/status. Duplicate-execution protection added via a service-property
lock + queued jobs.

### 8. proxyPanel module conversion
`Servers/ProxyPanel` — native rewrite of the WHMCS proxyPanel module. Lifecycle:
create/suspend/unsuspend/terminate/renew/upgrade/downgrade + sync + status lookup, plan/
location/protocol/quantity config, logging, error handling, queue support, operation retry.
Currently scaffolded against a documented API interface (`docs/modules/proxypanel.md`);
real endpoints wired when the admin-panel API spec is provided. **Status: scaffolded.**

### 9. Brazilian customer registration
`Others/BrazilianRegistration` — extra profile fields (Individual: CPF, RG; Business: Company
Name, Trade Name, CNPJ, State Registration + exempt flag), CPF/CNPJ validation rules, input
masks, encrypted-at-rest storage, and access control (policy) for sensitive documents.

### 10. Disable domain sales
Domain product type disabled via configuration + a policy/gate override and hidden UI, done
without editing core so upgrades are unaffected. Documented in `docs/10-disable-domains.md`.

### 11. Notification system
`Others/Notifications` — centralized dispatcher over Email + Telegram, queued with retry, for
customers and admins. Events: payments, tickets, provisioning, suspension, cancellation,
critical failures, webhooks, admin changes. Built on Laravel notifications + a Telegram channel.

### 12. Security
Cross-cutting — see `docs/12-security.md`: encrypted settings, webhook signature validation,
duplicate-operation guards, permission-based access (policies), audit logging (core
`owen-it/laravel-auditing` is present), no hardcoded secrets, sensitive-data protection.

## Phasing

- **Phase 0 (this delivery):** vendor core, repo structure, docs skeleton, install script,
  **CoinPayments gateway (reference slice)**, **proxyPanel module scaffold**.
- **Phase 1:** proxyPanel real API wiring + notifications + Brazilian registration.
- **Phase 2:** gateway rules + payment fees + Binance + Cryptomus config.
- **Phase 3:** client theme + admin panel customization + ticket enhancements.
- **Phase 4:** disable domains, security hardening pass, functional testing, final docs.

## Open items / needed from client

- Admin-panel (proxyPanel) **API specification**: base URL, auth scheme, and request/response
  for create/suspend/unsuspend/terminate/renew/upgrade/status. (Confirmed: client will provide
  API docs.)
- Cryptomus + Binance Pay merchant credentials (for integration testing).
- Brand assets (logo, colors) for the client-area theme.
