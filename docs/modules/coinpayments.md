# CoinPayments Gateway

Accept cryptocurrency payments through [CoinPayments](https://www.coinpayments.net) using the
CoinPayments API (`create_transaction`) for hosted checkout and IPN (Instant Payment
Notifications) for settlement.

- **Location:** `extensions/Gateways/CoinPayments/`
- **Type:** Payment Gateway
- **Signature scheme:** HMAC-SHA512
- **Idempotent:** yes (keyed on CoinPayments `txn_id`)

## Installation

The extension ships in this repository. Enable it in the admin panel:

1. **Admin → Extensions → Gateways**, enable **CoinPayments Gateway**, or run:
   ```bash
   php artisan app:extension:enable Gateways/CoinPayments
   ```
2. Create a **Gateway** using this extension and fill the settings below.

## Configuration

| Setting | Where to find it | Stored |
|---|---|---|
| **Merchant ID** | CoinPayments → Account → Account Settings → *Merchant Settings* | plain |
| **API Public Key** | CoinPayments → My Account → *API Keys* (needs `create_transaction`) | encrypted |
| **API Private Key** | shown once when you create the API key | encrypted |
| **IPN Secret** | Account Settings → *Merchant Settings* → IPN Secret | encrypted |
| **Receive Currency** | the coin you want to receive, e.g. `BTC`, `LTCT` (testnet), `USDT.TRC20` | plain |

### IPN URL

Set the **IPN URL** in CoinPayments (or per-transaction; the module sends it automatically) to:

```
https://YOUR-DOMAIN/extensions/coinpayments/ipn
```

Set **IPN Mode = HMAC** in Merchant Settings. The route is CSRF-exempt and authenticated purely
by the HMAC signature.

## How it works

1. **Checkout** — `pay()` calls `create_transaction` (request body signed with the private key,
   `HMAC` header) and receives a `checkout_url`, `txn_id`, deposit `address`, and `qrcode_url`.
   A *processing* transaction is recorded against the invoice, keyed on `txn_id`.
2. **Payment** — the buyer pays on CoinPayments. CoinPayments POSTs IPNs to the IPN URL as the
   payment progresses.
3. **Settlement** — `webhook()` validates the `HMAC` header (SHA-512 of the raw body against the
   IPN Secret) and the `merchant` id, then:
   - `status >= 100` or `status == 2` → **complete** → `addPayment()` (marks the invoice paid),
   - `status < 0` → **failed/cancelled** → `addFailedPayment()`,
   - otherwise → **pending** → `addProcessingPayment()`.

## Security & robustness

- **Signature validation** on every IPN, constant-time (`hash_equals`).
- **Merchant check** rejects IPNs meant for other accounts.
- **Idempotency / duplicate protection** — settlement uses `ExtensionHelper::addPayment()` with
  the `txn_id`, which `updateOrCreate`s the transaction inside a row-locked DB transaction, so
  repeated IPNs for the same payment never double-credit.
- **No hardcoded secrets** — all credentials are encrypted extension settings.
- **Logging** — every state transition and error is logged with `[CoinPayments]` context.

## Testing

Use CoinPayments testnet coin `LTCT` as the **Receive Currency**, create an invoice, and pay it
from a testnet wallet. Watch `storage/logs/laravel-*.log` for `[CoinPayments]` entries and
confirm the invoice moves to *Paid*.

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| IPN returns `Invalid signature` (400) | IPN Secret mismatch, or IPN Mode not set to HMAC |
| IPN returns `Invalid merchant` (400) | Merchant ID setting doesn't match the account |
| `CoinPayments API error: ...` on checkout | Public/private key wrong or missing `create_transaction` permission |
| Invoice stays pending | Payment still confirming on-chain, or IPN URL unreachable (check firewall/HTTPS) |
