# Core touchpoints

This project follows a **no-core-edits** rule: features live in `extensions/`, `themes/`
and config. This file lists the **rare, unavoidable** edits to vendored Paymenter core,
so they can be **re-applied after every upstream update** (see `docs/02-updates.md`).

> After `git merge upstream/*`, check each entry here and re-apply if the merge reverted it.
>
> **Every touchpoint below is currently applied in this repository.** Each was
> verified after applying — see `docs/VERIFICATION.md`.

---

## 1. Apply payment-method fees when a gateway is chosen (**applied**)

**Why:** Paymenter fires no event when a customer selects a gateway to pay an invoice,
so the Payment Method Fees module (spec item 6) has no hook to attach the fee. One call
in the payment component applies it. See `docs/modules/payment-fees.md`.

**File:** `app/Livewire/Invoices/Show.php`, method `payWithMethod($methodId)`.

**Change:** immediately **before** `$this->pay = ExtensionHelper::pay(...)`, insert:

```php
// Payment Method Fees (extensions/Others/PaymentFees) — apply gateway fee server-side.
if (class_exists(\Paymenter\Extensions\Others\PaymentFees\PaymentFees::class)) {
    $feeGateway = \App\Models\Gateway::find($methodId);
    if ($feeGateway) {
        \Paymenter\Extensions\Others\PaymentFees\PaymentFees::applyFee($feeGateway, $this->invoice);
        $this->invoice->refresh();
    }
}
```

**Notes:**
- Guarded by `class_exists`, so core still runs if the extension is removed.
- `applyFee()` is idempotent — safe to run every time the modal/gateway changes.
- Impact if not re-applied after an upgrade: fees simply stop being added (no breakage);
  the rest of the module (rules, admin, calculator) keeps working.

---

## 2. Enforce country-based gateway rules across ALL gateways (**applied**)

**Why:** Paymenter only filters gateways that implement `canUseGateway()`. Our own
gateways (CoinPayments, Binance) call the rules engine from that hook, so they're
already enforced. To also enforce rules for gateways that don't implement it
(Stripe, Cryptomus, …), filter the checkout list centrally. See
`docs/modules/gateway-rules.md`.

**File:** `app/Helpers/ExtensionHelper.php`, method `getCheckoutGateways(...)`.

**Change:** wrap the returned `$gateways` through the engine before returning:

```php
// Country-based Gateway Rules (extensions/Others/GatewayRules) — central enforcement.
if (class_exists(\Paymenter\Extensions\Others\GatewayRules\GatewayRules::class)) {
    $gateways = array_values(array_filter($gateways, fn ($g) =>
        \Paymenter\Extensions\Others\GatewayRules\GatewayRules::allows($g, $total, $currency, $type, $items)
    ));
}

return $gateways;
```

**Notes:**
- Applied. Without it, rules would only bind gateways that implement `canUseGateway()`.
- Guarded by `class_exists`; core still runs if the extension is removed.
- If not re-applied after an upgrade: only non-implementing gateways stop being
  filtered; our gateways and the admin UI keep working.

---

## 3. Discover admin widgets from extensions (**applied**)

**Why:** Paymenter auto-discovers `Resources` and `Pages` from enabled extensions, but
**not** `Widgets`. To show the `Others/AdminOps` dashboard widgets (spec item 2) — Shortcuts,
At a glance, Needs attention — discover extension widgets too. See `docs/02b-admin-area.md`.

**File:** `app/Providers/Filament/AdminPanelProvider.php`, inside the loop over enabled
extensions (next to the existing `discoverResources` / `discoverPages` calls).

**Change:** add one line:

```php
$panel->discoverWidgets(in: base_path('extensions/' . $extension->path . '/Admin/Widgets'), for: $extension->namespace . '\\Admin\\Widgets');
```

**Notes:**
- Applied. Without it the widget classes are never loaded, so the dashboard silently
  omits them (verified: `Filament::getPanel('admin')->getWidgets()` now lists
  `…\AdminOps\Admin\Widgets\Shortcuts`, `…\AtAGlance` and `…\ActionQueue` ahead of core's).
- Widget bodies load lazily over Livewire, so they are not in the initial page HTML.
- Admin primary **colour**: to match the brand, change `->colors(['primary' => Color::Blue])`
  to e.g. `->colors(['primary' => Color::hex('#e8365d')])` in the same provider. Purely
  cosmetic; optional.

---

## 4. Restored upstream migration lost during vendoring (**required**)

**Why:** the initial vendoring of Paymenter core (`c71388c`) dropped exactly one upstream
migration. Without it a **fresh install cannot complete**: `php artisan migrate --seed`
aborts with `SQLSTATE[HY000]: General error: 1 no such table: notification_templates`,
because `database/seeders/EmailTemplateSeeder.php` and `App\Models\NotificationTemplate`
target `notification_templates` while only the older `email_templates` table gets created.
Creating a user (`app:user:create`) fails for the same reason.

**File:** `database/migrations/2025_10_04_183152_rename_email_templates_to_notification_templates_table.php`

**Change:** restored byte-for-byte from upstream
(`Paymenter/Paymenter@master`). It renames `email_templates` →
`notification_templates` and adds `mail_enabled`, `in_app_enabled`, `in_app_title`,
`in_app_body`, `in_app_url`, `edit_preference_message`.

**Notes:**
- This is a **restoration**, not a customization — the file is identical to upstream, so a
  future `git merge upstream/master` will simply match it.
- Verified: after restoring, `migrate --seed` completes and seeds 12 notification templates.
- Root cause is the vendoring step, not upstream. When core is next re-vendored, diff the
  file list against upstream rather than copying selectively:
  `comm -23 <(upstream file list) <(local file list)`.

---

## 5. Show gateway refusals instead of a 500 (**applied**)

**Why:** `payWithMethod()` calls `ExtensionHelper::pay()` with no error handling. A gateway
refusing a payment is normal — Stripe rejects anything under 50c with `amount_too_small`,
gateways reject unsupported currencies, and any of them can be briefly down. Uncaught, the
exception renders as a bare **500 Server Error** page: the customer is told nothing, and
the sale is lost. Found on the live server with a $0.10 test invoice.

**File:** `app/Livewire/Invoices/Show.php`, method `payWithMethod($methodId)` plus a new
`gatewayErrorMessage()` helper.

**Change:** wrap the `pay()` call in try/catch, log the real error for the admin, and show
the customer a translated explanation. Wording lives in `lang/en/invoices.php`
(`gateway_error`, `amount_too_small`, `amount_too_large`, `gateway_unavailable`).

**Notes:**
- The raw exception is never shown — it can carry gateway internals and a stack trace.
  This includes credential fragments: Stripe answers a bad key with
  "Invalid API Key provided: sk_test_*ocal", so misconfiguration is matched and replaced
  with `gateway_misconfigured` *before* the gateway's own wording is echoed.
- If not re-applied after an upgrade, gateway refusals go back to being 500 pages.

---

## 6. Show gateway refusals on the credit-deposit page instead of a 500 (**applied**)

**Why:** `addCredit()` calls `ExtensionHelper::pay()` with no error handling, so the credit
page had the same defect #5 fixed on the invoice page. Any gateway refusal — a wrong API
key, an unsupported currency, an amount below the gateway minimum — escaped as a bare
**500 Server Error**. This is worse here than on an invoice: the deposit invoice is already
committed by the time `pay()` runs, so the customer was left with an orphaned unpaid
invoice, a stack trace, and no route back. Reproduced locally with a 999 USD deposit
against a gateway holding a placeholder key.

**File:** `app/Livewire/Client/Credits.php`, method `addCredit()` plus a new
`gatewayErrorMessage()` helper.

**Change:** wrap the `pay()` hand-off in try/catch, log the real error for the admin,
notify the customer with a translated explanation, and redirect to the deposit invoice
that was just created so they can retry or choose another method. Wording is reused from
`lang/en/invoices.php`, with one key added (`gateway_misconfigured`) for bad or missing
credentials — an operator problem the customer cannot fix by retrying.

**Notes:**
- Kept deliberately parallel to #5 so both payment entry points behave the same; if you
  change the message mapping in one, change it in the other.
- The raw exception is never shown — gateway errors can echo key fragments.
- If not re-applied after an upgrade, credit-deposit failures go back to being 500 pages.

---

## 7. Outstanding credit deposit is a notice, not a dead end (**applied**)

**Why:** Paymenter allows only one unpaid credit deposit at a time. Core enforced that by
throwing `DisplayException('You have an unpaid invoice for credits…')`, which renders as a
red error banner over a form the customer cannot submit — with no link to the invoice they
are being told to pay. The customer's intent at that moment is precisely to pay, so the
rule is right but the handling turned it into a dead end.

**File:** `app/Livewire/Client/Credits.php`, method `addCredit()`.

**Change:** look up the outstanding deposit invoice instead of just testing for existence,
roll back the open transaction, show an informational notice naming the invoice number and
amount outstanding, and redirect to that invoice with `?pay` so the payment modal opens on
arrival. Wording lives in `lang/en/account.php` (`credit_pending_invoice`).

**Notes:**
- The rollback matters: the check runs inside `DB::beginTransaction()`, so returning a
  redirect without it would leave the connection mid-transaction.
- `?pay` works because `Invoices\Show::$showPayModal` is URL-bound via `#[Url('pay')]`.
- The one-outstanding-deposit rule itself is unchanged — only how it is communicated.
- If not re-applied after an upgrade, the blocking error banner returns.

---

## 8. Sandbox credentials via `PAYMENT_MODE` (**applied**)

**Why:** gateway credentials live only in the database, so switching between sandbox and
the client's real keys meant editing every gateway by hand in the admin — easy to get
wrong, and it risks running development traffic against live keys. The client asked for an
env switch instead.

**File:** `app/Classes/Extension/Extension.php`, method `config($key)` plus a new
`developmentConfig()` helper. New file `config/payments.php` holds the mapping.

**Change:** when `PAYMENT_MODE=dev`, a gateway setting is read from
`config('payments.dev.<Extension>.<setting>')` (populated from `PAYMENT_DEV_*` in `.env`)
in preference to the stored value. Blank entries fall through to the database, so a single
field can be overridden alone. `PAYMENT_MODE=prod` — the default — ignores the dev block
entirely.

**Why here rather than per gateway:** `Extension::config()` is the single point every
gateway resolves settings through, so one change covers CoinPayments, Cryptomus, Binance
**and** the upstream Stripe extension without modifying any of them. Editing four gateway
classes — one of them vendored — would have been four places to re-apply on upgrade.

**Notes:**
- Restricted to `Paymenter\Extensions\Gateways\*`; server extensions are untouched.
- Read through `config()` rather than `env()` so it survives `php artisan config:cache`,
  where `.env` is no longer loaded.
- Defaults to `prod`, so a deployment that never sets `PAYMENT_MODE` behaves exactly as
  before — dev keys are strictly opt-in.
- If not re-applied after an upgrade, `PAYMENT_MODE` silently stops working and gateways
  fall back to the database. Verify with `docs/PAYMENT-KEYS.md`.

---

## 9. `app/Models/Plan.php` — a plan with no price in the visitor's currency was fatal

**What:** `Plan::price()` read `$price->setup_fee` and `$price->currency` straight off the
result of a lookup that can legitimately return `null`.

**Symptom:** `ErrorException: Attempt to read property "setup_fee" on null`, a 500 that took
out the storefront, the product page and checkout — not a degraded price, the whole page.

**When it happens:** a plan has one price row per currency, so there is none whenever a
visitor browses in a currency that plan was never priced in. Reproducible any time a product
is added before the hourly exchange-rate sync has written its BRL row, or a currency is
enabled after the catalogue was built.

**Fix:** `setup_fee` defaults to `0`; `price` and `currency` are left `null`.

**Why `currency` must stay null — the part that matters:** `Price` derives availability from
it (`'available' => $this->currency || $this->is_free`). Substituting a looked-up `Currency`
here — the obvious-looking fix — makes an unpriced plan report **available at 0.00**, i.e.
orderable for free. Leaving it null makes `available` false and renders "Not available in
your currency", which is the behaviour `Price` was already written to handle.

**Verified:** priced currencies unchanged (`USD $70.00`, `BRL R$370,62`, both available);
an unpriced currency returns `available=false` with no exception.

**If not re-applied after an upgrade:** the 500 returns for any product/currency combination
that lacks a price row.

---

## `App\Classes\Settings::flushCache()` — saved settings were invisible until the cache expired

**File:** `app/Classes/Settings.php` (new static method), called from
`app/Admin/Pages/Settings.php::save()`.

**Symptom:** two of them. Before the method existed, `save()` already called
`ClassesSettings::flushCache()` — on a plain class with no such method and no
`__callStatic`, so **every settings save fatalled** with
`Call to undefined method App\Classes\Settings::flushCache()`. With the call removed
instead, the original bug returns: a value written in the admin stays invisible.

**When it happens:** `SettingsProvider::getSettings()` reads every non-scoped setting from
the `settings` cache key and only falls back to the database when that key is empty. Saving
writes the row but left the cache intact, so the next request, the queue workers and the
auth pages all kept serving the old value until the key happened to expire.

**Fix:** `flushCache()` does `Cache::forget('settings')` and then
`SettingsProvider::getSettings(true)`. The forget alone is what fixes later requests — they
rebuild from the database on a miss. The forced reload is so the request that just saved
also sees its own change instead of finishing on the config it booted with.

**If not re-applied after an upgrade:** if `Admin\Pages\Settings::save()` still calls it,
every settings save is a 500. If both are reverted together, settings appear not to save.

---

## `config/logging.php` — daily log file mode, or the site 500s once a day

**File:** `config/logging.php`, `'permission' => 0666` on the `daily` channel.

**Symptom:** intermittent 500s on product and checkout pages, with **nothing in the log** —
because the logger is what failed. Reproducible by checking the day's file:
`ls -la storage/logs/laravel-$(date +%F).log` showing `root` ownership and mode `0644`.

**When it happens:** whichever process writes the first line of the day creates that day's
file and it keeps that process's ownership. In this container the scheduler and every
`php artisan` run are **root**, while web requests run as **nginx**. On any day root gets
there first, the file is `0644 root` and nginx can no longer append.

Writing the log is part of handling the request, so the failed write throws and the
response becomes a 500. It only shows on pages that log something — here that is any
product or checkout page, which logs the "ProxyPanel unreachable" warning — which is why
it looked like a fault in the order flow.

**Fix:** set the file mode explicitly so both users can share the file regardless of who
creates it. Ownership still varies; the mode is what matters.

**Not a substitute:** `chmod 666` on today's file fixes it until midnight only. Restarting
the container also clears it, because the entrypoint resets storage permissions — which is
what made this look intermittent and unrelated to any change.

**If not re-applied after an upgrade:** the 500s return, on a schedule, with an empty log.

---

## Admin panel: own login, renameable path

**Files:** `app/Providers/Filament/AdminPanelProvider.php`, `app/Http/Middleware/ImpersonateMiddleware.php`

**Why:** the client asked for the WHMCS separation — administrators sign in at the admin
panel, reach a customer's account only by impersonating, and never browse the client area as
themselves. Two of the three pieces cannot be done from an extension:

- **`->login()`** — without it Filament registers no login route, so an unauthenticated
  `/admin` redirects to `/login`, the customer login. Staff were being sent into the client
  area to reach the admin panel.
- **`->path(config('settings.admin_path') ?: 'admin')`** — the panel path is read when the
  panel registers its routes, which happens before any extension boots, so an extension
  cannot change it afterwards.

`ImpersonateMiddleware` hardcoded `admin/*`; it now reads the same setting, or renaming the
panel would leave impersonation active inside the admin panel itself.

**Rename the panel:**

```bash
php artisan app:settings:change admin_path myadmin
```

**Not a core edit:** keeping staff out of the client area is
`Others/PortalBehavior/Middleware/KeepStaffOutOfClientArea`, appended to the `web` group.

**If not re-applied after an upgrade:** the panel returns to `/admin` with no login page, and
staff are bounced through the customer login again. Impersonation still works either way.

---

## 10. `Summary` link on the customer list (**applied**)

**Why:** the WHMCS-style client summary (spec item 2 — `Others/AdminOps`, see
`docs/02b-admin-area.md`) needs an entry point, and the natural one is the customer list:
click a customer, see everything about them. The page itself is an extension page and needs
no core edit; only the link into it does.

**File:** `app/Admin/Resources/UserResource.php`, `table()` → `recordActions([...])`, plus
the `Filament\Actions\Action` and `…\AdminOps\Admin\Pages\ClientSummary` imports.

**Change:** spread a guarded action in ahead of `EditAction::make()`:

```php
...(class_exists(ClientSummary::class) ? [
    Action::make('summary')
        ->label('Summary')
        ->icon('heroicon-m-identification')
        ->url(fn (User $record): string => ClientSummary::getUrl(['record' => $record->getKey()])),
] : []),
```

**Why it cannot be done from the extension** — this was tried first, and it silently does
nothing. `Table::configureUsing()` is Filament's supported way to reach a table you do not
own, but it runs inside `Table::make()`; the resource's `table()` method runs *afterwards*
and calls `->recordActions([...])`, whose first statement is `$this->recordActions = []`.
Anything pushed from outside is discarded before the table renders. `->filters([...])` resets
the same way, which is why AdminOps' dashboard queue links to filters core already has
instead of adding its own. A Livewire component hook was also tried and did not fire in time.

**Notes:**
- Guarded by `class_exists`, so core still runs if AdminOps is removed.
- Verified: the rendered customer list carries one `/admin/client-summary/{id}` link per row
  and core's Edit action is untouched.
- If not re-applied after an upgrade: the summary page still exists and is still reachable
  by URL, but nothing links to it.

---

## 11. `->topNavigation()` on the admin panel (**applied**)

**File:** `app/Providers/Filament/AdminPanelProvider.php`

**Why:** the client asked for the WHMCS admin layout, which puts its menus in a bar across
the top rather than down the left side. Filament renders navigation groups as topbar
dropdowns when top navigation is on — the exact shape WHMCS's Clients / Orders / Billing /
Support menus need — and it is the one part of the skin that cannot be set from outside the
panel, because it is read while the panel is being constructed.

**Change:** one line, after `->spa()`:

```php
->topNavigation()
```

**Why it cannot be done from the extension:** `Panel::topNavigation()` is evaluated during
panel construction in the provider. Everything else the skin needs — the menus themselves,
the CSS, the left rail, the footer — is registered afterwards from
`Others/AdminOps` through `Filament::serving()` and render hooks, and needs no core change.

**Notes:**
- Filament keeps the sidebar in the DOM and translates it off-screen
  (`.fi-body-has-top-navigation .fi-sidebar { translate: -100% }`), where it still serves as
  the mobile drawer. There is no duplicated navigation column.
- Safe on its own: with AdminOps disabled this is a plain top bar carrying core's own
  navigation groups. Nothing becomes unreachable.
- If not re-applied after an upgrade: the panel reverts to a left sidebar carrying the WHMCS
  menu groups. Everything still works and every screen is still reachable — it just stops
  looking like the reference.

---

_(Everything else is implemented via extensions, themes, events, or configuration.)_
