# Payment keys — where they live and how to get them

Two sets of credentials, selected by one switch.

| `PAYMENT_MODE` | Credentials used | Stored in |
|---|---|---|
| `prod` *(default)* | the client's real keys | database — **Admin → Gateways** |
| `dev` | sandbox keys | `.env` — `PAYMENT_DEV_*` |

Set it in `.env`:

```dotenv
PAYMENT_MODE=dev
```

Only `PAYMENT_DEV_*` entries **with a value** take effect. A blank one falls through to the
database, so you can override a single field — a webhook secret for `stripe listen`, say —
without duplicating a whole gateway. With `PAYMENT_MODE=prod` the dev block is ignored
entirely, even if filled in.

After changing `.env`, run `php artisan config:clear`.

---

## Where each key is saved

- **Development / sandbox keys → `.env`.** Gitignored, so they never reach the repository.
  The full list of names is in `.env.example`, and the mapping to each gateway's settings
  is in `config/payments.php`.
- **Production keys → the database**, entered through Admin → Gateways. They are never in
  code or in `.env`, and Leandro can rotate them without a deploy.
- Local and the server keep **separate databases**, so their stored keys differ. This is
  why a gateway can work on the server and fail locally — see `docs/04-shared-dev-database.md`.

Nothing about this changes how a gateway reads its settings: `$this->config('key')` still
returns whatever is in force. The switch is applied centrally in
`app/Classes/Extension/Extension.php` — see `docs/CORE-TOUCHPOINTS.md` #8.

---

## Getting sandbox credentials

There are **no free public sandbox keys** for these providers. Every one issues credentials
only to a registered account — anything published online claiming otherwise is a leaked key
from someone's real account and must not be used. Each has to be registered for, and two of
the four are not instant.

### Stripe — free, instant, self-serve ✅

1. Create any Stripe account (no business verification needed for test mode).
2. Copy the **test** keys from <https://dashboard.stripe.com/test/apikeys>
   (`sk_test_…`, `pk_test_…`).
3. For the webhook secret, run `stripe listen --forward-to http://127.0.0.1:8080/extensions/stripe/webhook`
   and use the `whsec_…` it prints.
4. Test cards: `4242 4242 4242 4242`, any future expiry, any CVC.

Verify with `php scripts/verify-stripe.php`, then prove the full loop with
`php scripts/test-stripe-payment.php`.

### CoinPayments — free, self-serve ✅

New accounts use the **current** API (`c-api.coinpayments.net`), which authenticates with a
Client ID + Client Secret. It is *not* the legacy `merchant_id` / `public_key` /
`private_key` / `ipn_secret` scheme — that belongs to the old `www.coinpayments.net/api.php`
+ IPN system, which new signups do not get.

1. Create a free account, then open **API Integrations → new integration**.
2. Name it and set the webhook URL to your site's
   `/extensions/coinpayments/ipn` (it can be edited later).
3. On creation it shows the **Client ID** and **Client Secret** — the secret is displayed
   once. If you lose it, regenerate it; it cannot be retrieved.
4. Optionally restrict **Allowed IPs** to your server.
5. Testing uses **LTCT** (Litecoin testnet, currency id `1002`), which is enabled for
   payment and costs nothing.

Paste them into Admin → Gateways → CoinPayments (Client ID, Client Secret, API URL), or set
`PAYMENT_DEV_COINPAYMENTS_*` in `.env` with `PAYMENT_MODE=dev`.

Verify with `php scripts/test-coinpayments-payment.php`, then complete the payment by
opening the printed checkout link and paying with LTCT.

### Cryptomus — application required ⚠️

Keys are issued only after their team approves an application, and their documentation is
explicit that you need a working project to show or the key will not be granted. Not
instant. Apply at <https://cryptomus.com/>; the merchant UUID and payment API key then
appear under Business → Merchants → Merchant Settings.

Their webhook handling can be exercised separately through Cryptomus' test-webhook endpoint
before real keys arrive.

### Binance Pay — merchant onboarding required ⚠️

The hardest of the four. API keys are created in the Binance Merchant Admin Portal, which
requires an approved merchant account, and sandbox credentials must be requested from
Binance Merchant Operations. Binance signs its webhooks with an RSA certificate, so unlike
the others **nothing about it can be simulated** without real credentials.

---

## Practical order

1. **Stripe** — already working on the server, proven end to end (7/7).
2. **CoinPayments** — register today, test with LTCT; no waiting.
3. **Cryptomus** — start the application now, since approval takes time.
4. **Binance Pay** — start merchant onboarding now for the same reason; expect it to be
   last and to carry the most risk.

---

## Sources

- [Stripe test API keys](https://dashboard.stripe.com/test/apikeys)
- [CoinPayments — API authentication (current)](https://docs.coinpayments.net/api/auth)
- [CoinPayments — invoices API](https://docs.coinpayments.net/api/invoices)
- [CoinPayments — testing your integration (LTCT)](https://blog.coinpayments.net/tutorials/integration/integrating-coinpayments-step-4-testing-integration)
- [CoinPayments — account setup](https://blog.coinpayments.net/tutorials/integration/integrating-coinpayments-step-1-account-setup)
- [Cryptomus — merchant API docs](https://doc.cryptomus.com/merchant-api/payments/testing-webhook)
- [Binance Pay — authentication](https://developers.binance.com/docs/binance-pay/authentication)
- [Binance Pay — getting started (merchant)](https://merchant.binance.com/en/docs/getting-started)
