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
| **Sign-in protection** | The admin sign-in carries the same CAPTCHA as the client login, from the same setting and the same server-side verification — see below. Both are additionally rate-limited to 5 attempts. |

## CAPTCHA on the sign-in pages

**Admin → Settings → Security** picks one provider — reCAPTCHA v2 or v3, Turnstile or
hCaptcha — and one site key / secret pair. That single choice now covers **both** sign-ins:

| Page | Component |
|---|---|
| Client area — login, register, password reset, resend verification | Core's `App\Traits\Captchable` + the theme's `<x-captcha>` |
| **Admin panel — `/{admin_path}/login`** | `AdminOps\Admin\Auth\Login` + `adminops::captcha` |

There is deliberately **one** setting and one key pair. Two would mean two Google consoles,
two sets of allowed domains, and a way to have the storefront protected while the more
valuable door is not.

The admin page verifies with the same trait the client login uses, so a provider added to
core is picked up here without further work. The token is checked **after** the rate limiter,
not before: an unanswered challenge should still spend an attempt, or a bot that never solves
one gets unlimited free guesses at a password.

**It will not lock you out.** The challenge is enforced only when a provider *and* both keys
are present. Enabling the setting while a key is missing would otherwise leave a form that
cannot be satisfied, on the one page you would need in order to fix it — and the fix lives
inside the admin panel. If the keys are wrong rather than missing, the escape hatch is the
database:

```bash
php artisan app:settings:change captcha disabled
```

Everything else about the admin sign-in — separate login, renameable path, staff kept out of
the client area — is in `CORE-TOUCHPOINTS.md` under *"Admin panel: own login, renameable
path"*.

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
- [ ] CAPTCHA configured in **Settings → Security**, and the site key's allowed-domains list
      includes the admin host — the admin sign-in loads the same widget from the same key.
- [ ] `admin_path` moved off the default `admin`.

## Secrets handling for custom modules

New gateway/server modules **must**:
1. Declare credential fields with `'encrypted' => true`.
2. Read them via `$this->config('key')` — never inline.
3. Validate all inbound webhooks/IPNs before mutating state.
4. Log ids/refs and outcomes, **never** raw secrets or full card/crypto payloads.

### Declaring `encrypted` is not the same as being encrypted ⚠️

Checked on the dev server, 27 Aug 2026. Every gateway credential was stored **in plaintext**,
with the `settings.encrypted` column set to `0` — including Stripe's secret key and webhook
secret, CoinPayments' client secret and Binance's API secret:

```
extension     key                     encrypted  at_rest
Stripe        stripe_secret_key       0          PLAINTEXT
Stripe        stripe_webhook_secret   0          PLAINTEXT
CoinPayments  client_secret           0          PLAINTEXT
Binance       api_secret              0          PLAINTEXT
Cryptomus     payment_api_key         0          PLAINTEXT   ← fixed, now ENCRYPTED
```

Encryption at rest is driven by that **column**, not by the extension's config: the
`Setting` saving event encrypts only when the row says `encrypted`. Core sets the column
from the config — `'encrypted' => $option['encrypted'] ?? false` in
`GatewayResource/Pages/EditGateway.php` — but **only when the value is saved through
Admin → Gateways**. A value written any other way (a script, a seeder, direct SQL) keeps the
column's default of `0` and is stored as typed. Our `Cryptomus` config has always declared
`'encrypted' => true`; the row said otherwise.

The consequence is that anything holding the row holds the key: `mariadb-dump` output, the
nightly `/root/backups/*.sql.gz`, and any copy of the database taken for development.

Cryptomus was rewritten through the model with the flag from its own config and now reads
`ENCRYPTED` at rest. **The other three have not been touched** — re-saving a credential
means re-entering it, which is the owner's call, and doing it wrongly takes payments down.
To fix each: open **Admin → Gateways → *gateway* **, re-enter the secret, save. That path
sets the column correctly. Verify with:

```sql
SELECT e.extension, s.`key`, s.encrypted,
       CASE WHEN s.value LIKE 'eyJpdiI6%' THEN 'ENCRYPTED' ELSE 'PLAINTEXT' END AS at_rest
FROM settings s JOIN extensions e ON e.id = s.settingable_id
WHERE s.settingable_type LIKE '%Gateway';
```

Rotate anything that was ever stored in plaintext, since the old value may persist in a
backup taken before the fix.
