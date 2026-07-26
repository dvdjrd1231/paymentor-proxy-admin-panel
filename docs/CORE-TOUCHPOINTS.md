# Core touchpoints

This project follows a **no-core-edits** rule: features live in `extensions/`, `themes/`
and config. This file lists the **rare, unavoidable** edits to vendored Paymenter core,
so they can be **re-applied after every upstream update** (see `docs/02-updates.md`).

> After `git merge upstream/*`, check each entry here and re-apply if the merge reverted it.

---

## 1. Apply payment-method fees when a gateway is chosen

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

## 2. Enforce country-based gateway rules across ALL gateways (optional)

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
- Optional — skip it if you only use gateways that implement `canUseGateway()`.
- Guarded by `class_exists`; core still runs if the extension is removed.
- If not re-applied after an upgrade: only non-implementing gateways stop being
  filtered; our gateways and the admin UI keep working.

---

_(No other core touchpoints at this time. Everything else is implemented via extensions,
themes, events, or configuration.)_
