# Core touchpoints

This project follows a **no-core-edits** rule: features live in `extensions/`, `themes/`
and config. This file lists the **rare, unavoidable** edits to vendored Paymenter core,
so they can be **re-applied after every upstream update** (see `docs/02-updates.md`).

> After `git merge upstream/*`, check each entry here and re-apply if the merge reverted it.
>
> **All four touchpoints below are currently applied in this repository.** Each was
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
**not** `Widgets`. To show the `Others/AdminOps` Operations metrics widget (spec item 2)
on the admin dashboard, discover extension widgets too. See `docs/02b-admin-area.md`.

**File:** `app/Providers/Filament/AdminPanelProvider.php`, inside the loop over enabled
extensions (next to the existing `discoverResources` / `discoverPages` calls).

**Change:** add one line:

```php
$panel->discoverWidgets(in: base_path('extensions/' . $extension->path . '/Admin/Widgets'), for: $extension->namespace . '\\Admin\\Widgets');
```

**Notes:**
- Applied. Without it the widget class is never loaded, so the dashboard silently
  omits it (verified: `Filament::getPanel('admin')->getWidgets()` now includes
  `Paymenter\Extensions\Others\AdminOps\Admin\Widgets\OperationsOverview`).
- Stat values load lazily over Livewire, so they are not in the initial page HTML.
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

_(No other core touchpoints at this time. Everything else is implemented via extensions,
themes, events, or configuration.)_
