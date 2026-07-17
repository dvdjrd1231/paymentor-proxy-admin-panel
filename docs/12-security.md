# Security Practices & Checklist

Security requirements from the brief (item 12), and how this project satisfies them.

## Principles

| Requirement | Implementation |
|---|---|
| **Secure credential storage** | Gateway/server credentials are stored as **encrypted** extension settings (`'encrypted' => true`); app secrets live in `.env` (chmod 600, never committed). |
| **Webhook validation** | Every gateway validates provider signatures before acting. CoinPayments: HMAC-SHA512 over the raw body + merchant-id check, constant-time compare. Stripe: `Stripe-Signature` HMAC. |
| **Protection against duplicate operations** | Payments settle via `ExtensionHelper::addPayment()` keyed on the provider transaction id (`updateOrCreate` inside a row-locked DB transaction). Provisioning ops run under a per-service/per-action lock and short-circuit when already in the target state. |
| **Permission-based access control** | Filament policies + Paymenter roles gate admin actions; sensitive customer documents (CPF/CNPJ) are policy-guarded. |
| **Audit logging** | Core ships `owen-it/laravel-auditing`; provisioning actions emit audit events. |
| **Sensitive-info disclosure protection** | `APP_DEBUG=false` in production; errors logged, not shown; secrets never returned in API responses or logs (only ids/refs are logged). |
| **No hardcoded secrets** | Enforced by code review; all keys come from settings/`.env`. |

## Deployment hardening checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`.
- [ ] HTTPS enforced (Certbot `--redirect`), HSTS enabled at the proxy.
- [ ] `.env` is `chmod 600`, owned by `www-data`, and **not** in git.
- [ ] DB user scoped to the app database only; strong password.
- [ ] Redis bound to localhost (or password-protected).
- [ ] Firewall: expose only 80/443 (+ SSH); DB/Redis not public.
- [ ] Webhook endpoints reachable over HTTPS and CSRF-exempt **only** for the specific routes.
- [ ] Backups run nightly and are copied off-server; test a restore.
- [ ] `storage/` and `bootstrap/cache` writable by `www-data` only.
- [ ] Regularly `composer audit` and apply security updates (see `docs/02-updates.md`).

## Secrets handling for custom modules

New gateway/server modules **must**:
1. Declare credential fields with `'encrypted' => true`.
2. Read them via `$this->config('key')` — never inline.
3. Validate all inbound webhooks/IPNs before mutating state.
4. Log ids/refs and outcomes, **never** raw secrets or full card/crypto payloads.
