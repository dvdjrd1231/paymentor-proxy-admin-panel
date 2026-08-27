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
| CoinPayments on the **server** | **Complete** — invoice created at CoinPayments, their notification received and verified, settlement and crediting proven (section B2) |

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

Result on 14 Aug 2026 (re-verified on `https://paymenter-dev.7hoop.net`):

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

## B2. CoinPayments — proven end to end ✅

`php scripts/test-coinpayments-payment.php 5 --settle` — **13 of 13 checks pass** on
<https://paymenter-dev.7hoop.net>:

```
[ PASS ] checkout created at CoinPayments
[ PASS ] pending transaction recorded
[ PASS ] notification received and signature accepted     after ~9s
[ PASS ] forged signature refused over HTTPS              HTTP 400
[ PASS ] signed InvoicePaid accepted over HTTPS           HTTP 200
[ PASS ] invoice settled                                  status=paid
[ PASS ] transaction recorded against the invoice
[ PASS ] credit balance applied                           $5.00
[ PASS ] Paid + Completed do not double-credit            rows=1
[ PASS ] balance unchanged after the second event         $5.00
```

### What this proves, and what it does not

Two halves, tested differently:

- **Outbound and inbound are genuinely CoinPayments.** The invoice is created through their
  live API with the account's real credentials, and the `InvoiceCreated` notification that
  comes back is sent *by CoinPayments* and verified against the real client secret. Nothing
  is mocked.
- **Settlement is proven with a notification we send.** Paying for real requires LTCT sent
  to the checkout address, which no script can do. So the `InvoicePaid` is delivered by the
  test — but over real HTTPS to the live endpoint, signed with the account's real client
  secret, and shaped exactly like the notifications CoinPayments actually sends (the shape
  was captured from live deliveries, not invented). A forged signature is refused first, so
  acceptance is meaningful rather than a permissive endpoint.

**The one untested step is coins moving on-chain.** To close it, run the script without
`--settle`, open the printed checkout link, and pay with LTCT (Litecoin testnet, free —
currency id `1002`). The invoice should settle exactly as it does above.

Three real bugs were found and fixed getting here, none of which the documentation
predicted: the host (`c-api`, not `a-api`), PascalCase event names that would have stranded
every paid invoice at pending, and settlement keyed on the per-event notification id, which
would have written two payment rows for one payment.

## C. Credentials still needed (from Leandro)

**Stripe** and **CoinPayments** are configured and working on the server. The remaining two
are code-complete but have no credentials, so they are not set up there yet.

Each must be created under **Admin → Gateways**, then filled in:

### CoinPayments — **done**, credentials in place
Configured on the server with the client's API Integration (Client ID + Client Secret for
`c-api.coinpayments.net`). Nothing outstanding except regenerating the client secret, which
was shared over chat.

### Cryptomus — **done**, live and verified 27 Aug 2026

The application was approved and the account is active. Both credentials are configured on
the dev server and confirmed working end to end:

| Check | Result |
|---|---|
| `POST /v1/payment/services` with the stored key | HTTP 200, `state: 0`, **105 services, all available** |
| `scripts/test-cryptomus-payment.php` | **5/5** — invoice raised, payment created at Cryptomus, pending transaction recorded |

| Field | Where to get it |
|---|---|
| `merchant_id` | Merchant UUID, dashboard → Settings |
| `payment_api_key` | dashboard → API |

Two things worth recording, because neither is obvious from the dashboard:

- **The first key issued was already stale.** The server held an earlier key and every
  request came back `HTTP 401 Invalid Sign`. The merchant UUID was unchanged, so the failure
  looked like a signing bug rather than a credential one. If Cryptomus ever returns
  *Invalid Sign* against a UUID you know is right, compare the stored key with the issued
  one before touching the signing code.
- **Regenerate this key.** It was shared over chat, like the CoinPayments secret above.

Settlement still needs one real payment to confirm the callback end of it. The signature
scheme is `md5(base64(body) . api_key)` and we hold the key, so a signed callback can be
delivered deliberately — `scripts/test-cryptomus-payment.php --settle` does exactly that,
and rejects a forgery first so that acceptance means something.

### Binance Pay — **blocked by geo-restriction** 🚫

Credentials are configured (gateway id 10), but Binance Pay refuses the connection before
authentication is even attempted:

```
POST https://bpay.binanceapi.com/binancepay/openapi/certificates
HTTP 451
{"code":0,"msg":"Service unavailable from a restricted location according to
 'b. Eligibility' in https://www.binance.com/en/terms."}
```

Reproduced from **both** the VPS (`ipinfo` reports country `US`) and a developer machine,
and it happens on an unauthenticated request too — so this is location, not credentials.
The keys are untested rather than known-bad.

Nothing in the code can work around a 451. The options are:

1. **Run from a permitted region.** Either host the application somewhere Binance serves,
   or route only the outbound Binance API calls through a proxy in a permitted region.
   Inbound webhooks are unaffected — Binance calling us is not blocked, so a proxy for
   outbound traffic alone is sufficient.
2. **Ask Binance Merchant Operations** whether this merchant account can be enabled for the
   regions in use.
3. **Drop Binance** from the launch set.

This is a decision for Leandro; it cannot be resolved in the codebase. Note also that
Binance signs webhooks with its own RSA private key, so even once reachable, settlement can
only be confirmed by a real payment — unlike CoinPayments and Cryptomus, whose callbacks
are signed with a secret we also hold.

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
5. ~~**Domain + TLS.**~~ **Done** — the site is on <https://paymenter-dev.7hoop.net> behind
   Cloudflare, and both gateways were re-verified there. See `docs/05-domain-and-https.md`.
   Remaining hardening: the origin is still reachable on port 80 at the bare IP, and
   `trusted_proxies` is `*`; restrict both before production.

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
3. **Collect sandbox credentials for Cryptomus and Binance Pay** (section C) — the only
   thing blocking the remaining two.
4. **Sandbox each one** as its credentials arrive; Binance first, since it is the least
   proven. Re-run `scripts/test-stripe-payment.php` as the pattern for what "done" means.
5. ~~Delete the stale `/account/payment-methods` webhook endpoint~~ — **done**, removed
   along with the old IP-based endpoint when the domain moved.
6. **Switch Stripe to live keys** when going live, then re-run the end-to-end test.
   Update the CoinPayments dashboard webhook URL to the new domain as well.
