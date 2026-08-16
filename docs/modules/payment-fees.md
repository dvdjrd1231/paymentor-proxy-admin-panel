# Payment Method Fees

Spec item 6: charge an additional fee based on the **selected payment gateway** —
**fixed**, **percentage**, or **fixed + percentage** — with rules scoped by
**country, currency, product, customer type and invoice amount**. All calculation is
performed **exclusively on the server**.

- **Location:** `extensions/Others/PaymentFees/`
- **Type:** Others (mini-app with model, migration, Filament admin, calculator)

## How it works

1. Admins create **Payment Fee Rules** (Admin → Configuration → *Payment Fee Rules*).
2. When a customer chooses a gateway to pay an invoice, `PaymentFees::applyFee()`
   evaluates the rules **server-side** (`Support\FeeCalculator`) and adds/updates a
   single fee line item on the invoice — so the fee shows in the total and is
   recorded. Switching gateways replaces the fee (never stacks).

### Rule matching

Rules are evaluated by ascending `priority` (then id); the **first active rule whose
scope matches wins**. A blank scope field means "don't filter by this":

| Field | Matches when |
|---|---|
| Gateway | rule gateway is empty, or equals the chosen gateway (`extension` name) |
| Country | customer's `country` property equals the rule (ISO-2 or name, case-insensitive) |
| Currency | invoice currency equals the rule |
| Product | any invoice item's product id equals the rule |
| Customer type | `business` if the customer has a company name / CNPJ, else `individual` |
| Min / Max amount | invoice total is within the range |

### Fee formula (server-side)

```
fixed   → fee = fixed_amount
percent → fee = total × percent_amount / 100
both    → fee = fixed_amount + total × percent_amount / 100
```
Rounded to 2 decimals, never negative. See `Support/FeeCalculator.php`.

## Files

- `Models/PaymentFeeRule.php`, `database/migrations/*_create_payment_fee_rules_table.php`
- `Support/FeeCalculator.php` — the server-side calculation (unit-testable, no I/O)
- `Admin/Resources/PaymentFeeRuleResource.php` (+ Pages) — admin CRUD (auto-discovered)
- `PaymentFees.php` — extension: `feeFor()`, `applyFee()`

## Applying the fee — the one integration point

Fee **calculation and rule management** are fully inside the extension. The only place
the fee must be **applied** is when a gateway is chosen to pay an invoice, and
Paymenter fires **no event** there — so this needs one small call in the core invoice
payment component. It is documented in [`docs/CORE-TOUCHPOINTS.md`](../CORE-TOUCHPOINTS.md)
and is a two-line change re-applied after upgrades:

```php
// app/Livewire/Invoices/Show.php — inside payWithMethod(), before ExtensionHelper::pay():
$gateway = \App\Models\Gateway::find($methodId);
\Paymenter\Extensions\Others\PaymentFees\PaymentFees::applyFee($gateway, $this->invoice);
$this->invoice->refresh();
```

`applyFee()` is idempotent, so re-running it (e.g. the customer switches gateway) simply
recomputes the single fee line.

## Enable

```
Admin → Extensions → PaymentFees   (runs the migration)
Admin → Configuration → Payment Fee Rules   (create rules)
```

## Security

- All amounts computed server-side from the invoice and the customer's stored data;
  nothing is trusted from the browser.
- The fee is a normal invoice line item, so it flows through Paymenter's existing
  totals, tax, PDF and payment logic.

---

## Pre-filled rules from published provider rates

`scripts/seed-payment-fees.php` creates one rule per gateway, pre-filled with that
provider's own published merchant rate, and leaves every rule **inactive**:

| Gateway | Rule | Source |
|---|---|---|
| Stripe | 2.9% + $0.30 | published card rate |
| CoinPayments | 0.5% | 0.5% coins, 1% stablecoins and tokens |
| Cryptomus | 0.4% | entry tier; up to 2% on some merchant tiers |
| Binance Pay | 0% | no merchant fee to receive; payouts charged separately |

Nothing is charged to a customer until a rule is switched on in **Admin → Payment Fees**.
Whether to pass processing costs to the buyer at all — and at what margin — is a commercial
decision, so the figures are prepared and the switch is left to the client. Re-running the
script never overwrites a rule that has been edited or enabled.

Sources: [CoinPayments fees](https://coinspot.io/en/reviews/coinpayments/) ·
[Cryptomus fees](https://cryptomus.com/faq/how-much-is-a-fee-for-a-payer) ·
[Binance Pay fees](https://www.binance.com/en/support/faq/binance-pay-fees-6ff1944867e54b9a9576bce3109c7f7a)
