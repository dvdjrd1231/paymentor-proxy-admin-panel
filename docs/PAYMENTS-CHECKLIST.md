# Payment system — what is done, what is missing

Status of the payment side only, and exactly what is still needed to call it complete.
Facts below were read from the live server and the local checkout on 14 Aug 2026.

---

## A. Working and verified

| Area | State |
|---|---|
| Invoice → gateway → transaction → paid → credit/service applied | Verified (106-check suite) |
| Credit deposits (invoice created, balance applied, spendable) | Verified |
| Duplicate-payment protection, idempotency via `transaction_id` | Verified |
| Gateway refusals show a message, never a 500 page | Verified both on the invoice page and the credits page |
| Credential leakage in error text | Fixed — refusals no longer echo key fragments |
| Per-gateway minimum amounts | Implemented (`minimum_amount` setting on each gateway) |
| Payment fees per gateway | `Others/PaymentFees` present and applied server-side |
| Stripe on the **server** | Real test keys, authenticates (`acct_1TWnmp4PybDAYlv3`), can create payment intents, webhook secret set, endpoint publicly reachable |

The transaction chain has been proven by recording payments the way a gateway webhook does.
That exercises everything **except** the third-party call and the inbound webhook.

---

## B. The one blocking gap — no real payment has ever completed

Evidence from the live server:

- `invoice_transactions` contains **one** row: `$1.00`, gateway `(none)`, `is_credit_transaction = 1`.
  That is an internal credit entry, not a card charge.
- **Zero** requests to `/extensions/stripe/webhook` in 30 days of container logs.

So checkout → Stripe → **webhook** → invoice paid → credit/service applied has never run
once, end to end, with a real card. Everything else is verified; this is not.

**This needs no credentials from anyone and should be done first.**

1. Open `http://69.197.186.115`, sign in, add a credit deposit (or order a service).
2. Pay with Stripe using test card `4242 4242 4242 4242`, any future expiry, any CVC.
3. Confirm afterwards:
   - the invoice flips to **paid**,
   - a transaction row appears with the Stripe gateway and a real `pi_…` id,
   - the credit balance or the service state updates.

Until that passes, the payment system is not proven regardless of how the code reads.
If the invoice stays *pending* after a successful card, the webhook is the fault — check
Stripe Dashboard → Developers → Webhooks for delivery attempts and response codes.

---

## C. Credentials still needed (from Leandro)

Only **Stripe** exists on the server. The other three gateways in scope are not set up
there at all — they exist in the codebase and in the local database only.

Each must be created under **Admin → Gateways**, then filled in:

### CoinPayments
| Field | Where to get it |
|---|---|
| `merchant_id` | Account → Account Settings |
| `public_key` | Account → API Keys |
| `private_key` | Account → API Keys |
| `ipn_secret` | Account → Merchant Settings → IPN Secret |
| `receive_currency` | business decision (e.g. USDT, BTC) |

### Cryptomus
| Field | Where to get it |
|---|---|
| `merchant_id` | Merchant UUID, dashboard → Settings |
| `payment_api_key` | dashboard → API |

### Binance Pay
| Field | Where to get it |
|---|---|
| `api_key` | Certificate SN, Binance Merchant portal |
| `api_secret` | Binance Merchant portal |
| `pay_currency` | business decision |
| `sandbox_url` | only if testing against sandbox first |

**Binance has never been exercised at all** — it signs webhooks with an RSA certificate,
which cannot be simulated without real sandbox credentials. It carries the most risk of
the four.

### Stripe — before go-live
Currently **test** keys (`sk_test_…`). Live keys are required before taking real money,
and the webhook must be re-pointed at the production domain when the site moves off the
bare IP.

---

## D. Business decisions needed (not technical blockers, but they gate completion)

1. **Which gateways actually launch?** Four are in scope; each one added is another set of
   credentials, another webhook to keep alive, and another failure mode to support.
2. **Currency.** Only **USD** is configured (`default_currency = USD`, one currency record).
   For a Brazilian customer base, confirm whether BRL is required — this affects pricing,
   gateway support, and what Stripe/Cryptomus can settle in. It is not currently set up.
3. **Minimum deposit / maximum balance.** Now `5` / `999` / `9999` (min deposit, max
   deposit, max balance). Confirm these are the intended commercial limits.
4. **Per-gateway fees.** `PaymentFees` is active — confirm the fee per method, since it is
   added to the customer's invoice (a $500 deposit currently invoices $511.50).
5. **Domain + TLS.** The site runs on `http://69.197.186.115` over plain HTTP. Card
   payments and webhooks should be on HTTPS with a real domain before launch.

---

## E. Environment notes that will otherwise waste time

- **Local and server databases are different.** Gateway keys live in the database, not
  `.env`. Local holds 13-character placeholders (`sk_test_local`); the server holds the
  real test keys. Testing payments locally against the local database will always fail on
  authentication. See `docs/04-shared-dev-database.md`.
- **Webhooks cannot reach `127.0.0.1`.** For local end-to-end work, forward them:
  `stripe listen --forward-to http://127.0.0.1:8080/extensions/stripe/webhook`.
  The shared database complicates this — the stored webhook secret belongs to the server,
  so prefer testing the full loop on the server.
- **Do not blank Stripe's webhook secret field.** `Stripe::updated()` asks Stripe to create
  a webhook endpoint whenever that field is empty, and Stripe rejects local URLs, so
  clearing it makes saving the gateway fail.
- **Run `php scripts/verify-stripe.php`** after any key change; it confirms the key
  authenticates and a payment intent can be created, without printing the key.

---

## Priority order

1. **Prove one real Stripe payment on the server** (section B) — no credentials needed.
2. **Decide the launch gateway list and currency** (section D 1–2).
3. **Collect credentials for the chosen gateways** (section C).
4. **Sandbox each one** as its credentials arrive; Binance first, since it is the least
   proven.
5. **Switch Stripe to live keys, move to a domain with HTTPS, re-point webhooks.**
