# Installing modules and themes

Covers the two topics the specification names separately: **module installation** and
**theme installation**. Everything here works without touching Paymenter core, so an
upstream upgrade does not undo it.

---

## Modules (extensions)

Every custom feature in this project is a Paymenter extension. They live under:

| Path | Type | Modules |
|---|---|---|
| `extensions/Gateways/` | payment | Stripe, CoinPayments, Cryptomus, Binance |
| `extensions/Servers/` | provisioning | ProxyPanel |
| `extensions/Others/` | cross-cutting | GatewayRules, PaymentFees, Notifications, TicketTools, BrazilianRegistration, ProvisioningOps, AdminOps, Announcements, Affiliates |

### Installing

A module is installed by **enabling it**, which runs its `installed()` hook and applies its
own migrations.

**Admin → Extensions** for `Others`; **Admin → Gateways** / **Admin → Servers** for payment
and provisioning modules, which are created there rather than listed under Extensions.

> `ExtensionHelper::getInstallableExtensions()` returns only the `other` type, so gateways
> and servers deliberately do not appear on the Extensions page. Create them from their own
> admin section instead.

### Installing from the command line

```bash
php artisan app:extension:install other TicketTools
php artisan app:extension:install gateway CoinPayments
```

There is no `app:extension:enable`. If a module's row already exists but is disabled,
enable it in the admin, or:

```php
$e = App\Models\Extension::updateOrCreate(
    ['extension' => 'TicketTools'],
    ['name' => 'Ticket Tools', 'type' => 'other', 'enabled' => true],
);
App\Helpers\ExtensionHelper::call($e, 'installed', mayFail: true);   // applies migrations
```

`name` is required — the column has no default, and omitting it fails with
*"Field 'name' doesn't have a default value"*.

### After installing

```bash
php artisan config:clear
php artisan route:list | grep extensions      # confirm the module registered its routes
```

Restart the app container if routes were added — extension routes are registered in
`boot()`, which runs once per process.

### Verifying

Each module ships its own documentation and, where it talks to a third party, a test
script:

```
php scripts/test-stripe-payment.php 10
php scripts/test-coinpayments-payment.php 5 --settle
php scripts/test-cryptomus-payment.php 5 --settle
php scripts/test-binance-payment.php 5
php scripts/test-tickets.php
php scripts/verify-stripe.php
```

Per-module docs are in `docs/modules/`.

### Removing

Disable in the admin. `uninstalled()` rolls back the module's migrations, so **its data is
dropped** — take a backup first (`scripts/backup.sh`).

---

## Themes

## Catalogue management

Categories and products are database records, so the storefront and product detail pages
always use the current values saved in the admin panel. Edit them from the admin catalogue
screens; changes to names, descriptions, category placement, prices, and visibility are
immediately reflected on the client area after clearing the view cache if needed.

The optional `scripts/seed-catalogue.php --apply` command only creates missing proxy
categories/products and initializes their ProxyPanel settings. Existing category and product
content is preserved, including admin edits and hidden products. It is safe to use as a
bootstrap/synchronization command without making the script the source of truth for storefront
copy.

## Login and registration CAPTCHA

CAPTCHA is rendered on the login and registration forms and verified server-side on every
submission. Configure it in **Admin → Settings → Security**:

1. Select a provider and version.
2. Enter the matching site key and secret from that provider's dashboard.
3. Clear the application/config cache and reload the auth page.

The message `This reCAPTCHA is for testing purposes only` is emitted by Google when the
configured site key is one of Google's public test keys. It is expected for local testing,
but it must be replaced with a site key registered for the application's real host before
production. The site's allowed domain must include the exact hostname, without the scheme or
port.

Themes use `qirolab/laravel-themer`. Views are overridden by file path; no core file is
edited.

```
themes/
  default/views/...      untouched upstream theme, useful as reference
  proxy/views/...        this project's WHMCS-style client area
```

### Installing a theme

1. Copy the theme folder into `themes/`.
2. Set it as active in **Admin → Settings → Theme**.
3. `php artisan view:clear`

### How overriding works

A theme only needs the views it changes; anything absent falls through to `default`. To
restyle one page, copy just that file:

```
themes/default/views/invoices/show.blade.php   →   themes/proxy/views/invoices/show.blade.php
```

The proxy theme's styling is one file — `themes/proxy/views/layouts/whmcs-css.blade.php` —
with the palette at the top:

```css
:root {
    --brand: #e8365d;
    --brand-dark: #c72b4c;
    --brand-contrast: #ffffff;
}
```

Changing `--brand` re-colours the header bar, buttons, panel headings, stat tiles and links
together. Layout is responsive by default: the two-column client area collapses to one
column below 900px, and the stat tiles reflow via
`grid-template-columns: repeat(auto-fit, minmax(160px, 1fr))`.

### In Docker

`themes/` is a bind mount, so edits take effect on the next request. After changing Blade
files:

```bash
docker exec paymentor-proxy-admin-panel-paymenter-1 php artisan view:clear
```

`public/` and `config/` are **not** mounted — files placed there are lost when the
container is recreated. That is why the Cryptomus domain-verification file is served from a
gateway setting rather than dropped in `public/`.

---

## Troubleshooting

| Symptom | Cause |
|---|---|
| Module enabled but its routes 404 | `boot()` has not run — restart the container |
| "Field 'name' doesn't have a default value" | `name` omitted when creating the extension row |
| Theme change not visible | `php artisan view:clear` |
| Setting saved but not taking effect | settings are cached — `Cache::forget('settings')`, then `config:clear` |
| Array-typed setting read as a string | the row needs `type => 'array'`; values are cast on retrieval by `App\Events\Setting\Retrieved` |
