# CLAUDE.md — repo guidance

Customized [Paymenter](https://paymenter.org) (Laravel 12 / Filament 5 / Livewire 4, PHP 8.3+)
deployment that replaces WHMCS for an IPv6 proxy business. Upstream Paymenter is **vendored** at
the repo root; our work is layered on top **without editing core**.

## Golden rules
- **Do not edit vendored core** (`app/`, `bootstrap/`, `config/`, `database/`, `resources/`,
  `routes/`, core `public/`). Add features as extensions/themes instead. Any unavoidable core
  touch goes in `docs/CORE-TOUCHPOINTS.md`.
- **No secrets in code.** Credentials are encrypted extension settings (`'encrypted' => true`) or
  `.env`. Read via `$this->config('key')`.
- **Validate every webhook/IPN** before mutating state; keep operations **idempotent**.
- All money/fee/availability logic runs **server-side**.

## Where things live
- `extensions/Gateways/<Name>/` — payment gateways (extend `App\Classes\Extension\Gateway`).
- `extensions/Servers/<Name>/` — provisioning (extend `App\Classes\Extension\Server`).
- `extensions/Others/<Name>/` — cross-cutting mini-apps (fees, rules, notifications, BR reg.).
- `themes/` — client-area themes (Blade override via `qirolab/laravel-themer`).
- `scripts/` — deployment/backup automation. `docs/` — delivery docs.

## Extension contracts (verified against source)
- **Gateway:** `getConfig()`, `pay($invoice,$total)` (returns URL or View), `webhook(Request)`;
  record payments via `App\Helpers\ExtensionHelper::addPayment/addProcessingPayment/addFailedPayment`
  (idempotent when passed a `$transactionId`). Availability hook: `canUseGateway($total,$currency,$type,$items)`.
  Webhook routes go in the extension's `routes.php` (CSRF-exempt), name `extensions.gateways.<name>.webhook`.
- **Server:** duck-typed lifecycle — `createServer/suspendServer/unsuspendServer/terminateServer/upgradeServer($service,$settings,$properties)`
  (start each with `$settings = array_merge($settings,$properties)`), plus `getConfig()`,
  `testConfig()`, `getProductConfig($values)`, `getCheckoutConfig()`, `getActions($service)`.
  **There is no `renew` hook** — renewal is billing-driven. Only implement methods the backend
  supports (`ExtensionHelper::hasFunction()` gates them).
- **Events:** subscribe in `boot()` via `Event::listen(App\Events\...)`.

## Commands
- Enable/disable: `php artisan app:extension:enable Gateways/CoinPayments`.
- Routes: `php artisan route:list | grep extensions`.
- Lint PHP quickly: `php -l <file>`.
- Local dev needs **PHP 8.3+** (this workstation has 8.2 → boot on the server / a 8.3 container).

## Status
See `README.md` status matrix and `docs/00-project-plan.md`. Authored files: `docs/AUTHORED-FILES.md`.
