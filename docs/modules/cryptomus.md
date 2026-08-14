# Cryptomus Gateway

Accept cryptocurrency payments via [Cryptomus](https://cryptomus.com) — hosted payment
page + signed webhook settlement.

- **Location:** `extensions/Gateways/Cryptomus/`
- **Type:** Payment Gateway
- **Signing:** `md5(base64(json) . payment_api_key)` (Cryptomus scheme)
- **Idempotent:** yes (keyed on the Cryptomus payment `uuid`)

> The brief lists Cryptomus under *"Configuration"*. Paymenter ships no Cryptomus
> gateway and no maintained, Filament-5-compatible community one exists, so this project
> provides a first-party Cryptomus gateway built to the same standard as the others.
> You then just **configure** it with your merchant credentials.

## Configuration

1. In the Cryptomus dashboard, note your **Merchant UUID** and create a **Payment API key**.
2. Enable **Cryptomus Gateway** under **Admin → Extensions → Gateways** (or
   **Admin → Extensions**; enabling there runs the extension's `installed()` hook).
3. Create a Gateway using it and set **Merchant UUID** and **Payment API Key**
   (encrypted).
4. In Cryptomus, set the **webhook / callback URL** to:
   ```
   https://YOUR-DOMAIN/extensions/cryptomus/webhook
   ```

## How it works

1. **Checkout** — `pay()` calls `POST /v1/payment` (signed) and receives a hosted
   payment `url`. A *processing* transaction is recorded, keyed on the payment `uuid`.
2. **Payment** — the buyer pays on Cryptomus.
3. **Settlement** — `webhook()` verifies the `sign` field
   (`md5(base64(body without sign) . api key)`), then:
   - `paid` / `paid_over` → `addPayment()` (invoice paid),
   - `cancel` / `fail` / `system_fail` / `wrong_amount` → `addFailedPayment()`,
   - otherwise → still processing.

## Security & robustness

- **Sign verified** on every webhook (constant-time compare); invalid → 400.
- **Idempotency / duplicate protection** — settlement keys on the payment `uuid` via
  `addPayment()` (row-locked `updateOrCreate`), so repeat callbacks never double-credit.
- Credentials are **encrypted** settings; never hard-coded; secrets never logged.
- Honors the **Country-based Gateway Rules** module (`canUseGateway()`).

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| Webhook returns `Invalid sign` | Payment API key mismatch, or a proxy altered the body |
| `Cryptomus API error` on checkout | Wrong Merchant UUID / API key |
| Invoice stays pending | Callback URL not set in Cryptomus, or unreachable over HTTPS |
