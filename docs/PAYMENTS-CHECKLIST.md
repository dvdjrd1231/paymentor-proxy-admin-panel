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
| Stripe on the **server** | **Complete** — real test payment settled by Stripe's own webhook, invoice paid, credit applied (section B) |

The transaction chain is proven twice over: by recording payments the way a gateway webhook
does (all four gateways' internal handling), and — for Stripe — by a real card payment that
Stripe itself settled via webhook. The remaining gateways still need their third-party leg
tested, which requires sandbox credentials.

---

## B. Stripe — proven end to end ✅

Run `php scripts/test-stripe-payment.php [amount]` on the server. It creates a deposit
invoice, hands off to Stripe exactly as the checkout does, pays with a test card through
Stripe's API, then waits for Stripe's own webhook to settle the invoice. It refuses to run
against live keys.

Result on 14 Aug 2026 (`http://69.197.186.115`):

```
[ PASS ] deposit invoice created                   #INV-12 for 25 USD
[ PASS ] gateway hand-off (payment intent created)
[ PASS ] intent carries invoice_id metadata        pi_3U4OGY4PybDAYlv30vAAgycS
[ PASS ] card payment confirmed at Stripe          status=succeeded
[ PASS ] webhook arrived and marked the invoice paid   after ~2s
[ PASS ] transaction recorded against the invoice  pi_3U4OGY4PybDAYlv30vAAgycS (25.00)
[ PASS ] credit balance applied                    $25.00
7 of 7 steps passed
```

### The webhook was misconfigured — fixed

The first run failed at the webhook: the card charged successfully at Stripe but the
invoice stayed *pending*. Two endpoints were registered on the Stripe account and **neither
was correct**:

| Registered URL | Problem |
|---|---|
| `http://69.197.186.115/account/payment-methods` | wrong path — a client-area page, not the webhook route |
| `https://ai4senior-info.vercel.app/api/stripe-webhook` | a different project sharing the same Stripe account |

The route the extension actually serves is `/extensions/stripe/webhook`. A correct endpoint
was registered (`we_1U4OFz4PybDAYlv3zOAcQ5Go`) and its signing secret stored, after which
the loop closed in ~2 seconds.

**Still to tidy:** the `/account/payment-methods` endpoint is this project's mistake and
should be deleted in the Stripe dashboard — it will keep collecting failed deliveries.
Leave the vercel endpoint alone; it belongs to another project on the same account.

This is the check to re-run after any key, URL or domain change — it is the only one that
covers the outbound call and the inbound webhook together.

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

1. ~~Prove one real Stripe payment on the server~~ — **done**, 7/7 (section B).
2. **Decide the launch gateway list and currency** (section D 1–2).
3. **Collect sandbox credentials for the chosen gateways** (section C) — this is now the
   only thing blocking the remaining three.
4. **Sandbox each one** as its credentials arrive; Binance first, since it is the least
   proven. Re-run `scripts/test-stripe-payment.php` as the pattern for what "done" means.
5. **Delete the stale `/account/payment-methods` webhook endpoint** in Stripe.
6. **Switch Stripe to live keys, move to a domain with HTTPS, re-point webhooks**, then
   re-run the end-to-end test against the new domain.
