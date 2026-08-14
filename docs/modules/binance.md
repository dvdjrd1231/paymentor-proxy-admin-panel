# Binance Pay Gateway

Accept cryptocurrency payments via **Binance Pay** using the **official Merchant API
(v3)** only. Hosted checkout + signed webhook settlement.

- **Location:** `extensions/Gateways/Binance/`
- **Type:** Payment Gateway
- **Request signing:** HMAC-SHA512 (Binance scheme) · **Webhook:** RSA (Binance cert)
- **Idempotent:** yes (keyed on our `merchantTradeNo`)

## Requirements

A Binance **Merchant** account with Binance Pay enabled, and API credentials
(**API Key / Certificate SN** and **API Secret**) from the merchant dashboard.

## Configuration

1. Enable **Binance Pay Gateway** under **Admin → Extensions → Gateways** (or
   **Admin → Extensions**; enabling there runs the extension's `installed()` hook).
2. Create a Gateway using it and set:
   - **API Key (Certificate SN)** — encrypted,
   - **API Secret** — encrypted,
   - **Order Currency** — e.g. `USDT`, `BUSD`, `EUR` (must be enabled on your account),
   - **Test Mode** — see § Test mode below,
   - **Sandbox API URL** — only used when Test Mode is on.
3. In the Binance Merchant dashboard, set the **webhook URL** to:
   ```
   https://YOUR-DOMAIN/extensions/binance/webhook
   ```

## How it works

1. **Checkout** — `pay()` calls `POST /binancepay/openapi/v3/order` (signed
   HMAC-SHA512) and receives `checkoutUrl` + `qrcodeLink`. A *processing* transaction
   is recorded against the invoice, keyed on a unique `merchantTradeNo`.
2. **Payment** — the buyer pays via the Binance app / hosted page.
3. **Settlement** — Binance POSTs a webhook. `webhook()` verifies the **RSA signature**
   against Binance's public certificate (fetched from `/binancepay/openapi/certificates`
   and cached), reconciles to the invoice via the stored `merchantTradeNo`, then:
   - `PAY_SUCCESS` → `addPayment()` (invoice paid),
   - `PAY_CLOSED / PAY_EXPIRED / PAY_ERROR` → `addFailedPayment()`.
   Responds `{"returnCode":"SUCCESS","returnMessage":null}` as Binance requires.

## Security & robustness

- Requests signed HMAC-SHA512; **webhook verified with Binance's RSA certificate**.
- **Idempotency / duplicate protection** — settlement uses `addPayment()` with the
  `merchantTradeNo`, an `updateOrCreate` inside a row-locked transaction, so repeated
  webhooks never double-credit.
- Credentials are **encrypted** settings; never hard-coded; secrets never logged.
- Honors the **Country-based Gateway Rules** module (`canUseGateway()`).

## Test mode

Binance does **not** publish a single global sandbox host — sandbox access is issued per
merchant along with test credentials. So the toggle works like this:

| Setting | Effect |
|---|---|
| **Test Mode** off (default) | All API calls go to `https://bpay.binanceapi.com` (live). |
| **Test Mode** on | Calls go to **Sandbox API URL**. If that is blank, it falls back to `https://bpay.binanceapi.com`. |

Test Mode also stamps `test_mode` on the `[Binance]` "Created Binance Pay order" log line,
so a test order is identifiable in the payment log.

> Enter the sandbox host Binance gave you with your test merchant account in **Sandbox API
> URL**, and use the matching test API key/secret. If you have no sandbox account, leave
> Test Mode off and test with a live merchant account and a small real order, watching
> `storage/logs/laravel-*.log` for `[Binance]` entries.

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| Webhook returns `Invalid signature` | Wrong credentials, or certificate fetch failed (check logs) |
| `Binance Pay API error` on checkout | API key/secret wrong, or currency not enabled on the account |
| Invoice stays pending | Webhook URL not set in the merchant dashboard, or unreachable over HTTPS |
