# Verification record

What has actually been executed against a running install, and what has not. Anything not
listed here as verified should be treated as unverified.

**Environment used:** PHP 8.4.24, SQLite, `php artisan serve`, all extensions enabled,
theme `proxy`.

---

## 1. Fresh install

| Check | Result |
|---|---|
| `composer install` on a clean checkout | ✅ |
| `npm install && npm run build` | ✅ |
| `php artisan migrate --seed` | ✅ — **failed before** touchpoint #4 (see below) |
| `php artisan app:user:create …` | ✅ — **failed before** touchpoint #4 |
| All 10 extensions enable and run their migrations | ✅ |
| Webhook/callback routes registered | ✅ 4 routes |

> **Found and fixed:** the vendoring of core dropped one upstream migration
> (`2025_10_04_183152_rename_email_templates_to_notification_templates_table.php`), which
> made `migrate --seed` **and** first-user creation fail on any clean environment. See
> `docs/CORE-TOUCHPOINTS.md` #4.
>
> **Found and fixed:** `php artisan app:extension:enable` — documented in 9 places —
> **does not exist**. The command list only has `install`, `disable`, `create`, `upgrade`.
> Docs now describe the real procedure (Admin → Extensions).
>
> **Worth knowing:** Paymenter takes its canonical URL from the `app_url` **database
> setting**, not `.env APP_URL`. If they disagree, every request 302s to the setting's
> host. Set it with `php artisan app:settings:change app_url "<url>"`.

## 2. Provisioning lifecycle (16/16 checks)

Run with deliberate fault injection (panel unreachable / returning errors).

| Check | Result |
|---|---|
| `createServer` provisions and stores the panel service id | ✅ |
| Proxy credentials stored | ✅ |
| Proxy addresses + host cached for the client area | ✅ |
| **Panel broken:** error surfaced, not swallowed | ✅ |
| **Panel broken:** failure recorded in Services → Provisioning | ✅ |
| **Panel broken:** service NOT left silently `active` (reverted to `pending`) | ✅ |
| **Panel broken:** no panel id stored for a failed create | ✅ |
| Retry (the admin button's code path) provisions the service | ✅ |
| Retry closes the failure row; attempts counted | ✅ |
| `terminateServer` fires the destroy call; panel shows `cancelled` | ✅ |
| Local panel id cleared on terminate | ✅ |
| Repeat terminate is a no-op (idempotent) | ✅ |

The "order is silently activated" case is reproduced faithfully: the test sets the service
to `active` first, exactly as `RenewServiceService` does before the queued job runs.

## 3. Panel callback

| Check | Result |
|---|---|
| No secret configured → endpoint refuses (403) | ✅ |
| Missing / wrong secret → 401 | ✅ |
| Valid `X-Panel-Secret` → 200 | ✅ |
| Valid `X-Panel-Signature` (HMAC-SHA256 of raw body) → 200 | ✅ |
| `suspended` / `active` / `terminated` update the service status | ✅ |
| Service resolved by Paymenter `service_id` | ✅ |
| Service resolved by the panel's own id | ✅ |
| Unrecognised state is **not** applied, and is recorded for the admin | ✅ |

## 4. Payments (10/10 checks)

| Check | Result |
|---|---|
| Both new gateways offered at checkout | ✅ |
| Gateway rule hides a gateway above an amount threshold | ✅ |
| Same gateway still offered below the threshold | ✅ |
| Other gateways unaffected by the rule | ✅ |
| Payment fee calculated (fixed + percentage) | ✅ |
| Invoice total increases by exactly the fee | ✅ |
| Re-applying the fee is idempotent | ✅ — **bug found and fixed** |
| Transaction recorded for the admin log | ✅ |
| Duplicate notification does not duplicate the row | ✅ |
| Settlement updates the same transaction row | ✅ |

> **Bug found and fixed:** `PaymentFees::applyFee()` was documented as idempotent but
> compounded on every call (100 → 103.50 → 103.57 …). It deleted the old fee line but then
> calculated the percentage against a stale in-memory total that still included it. Since
> the fee is applied every time the customer switches gateway in the payment modal, this
> would have overcharged customers in production. Fixed by refreshing the invoice between
> the delete and the calculation.

Webhook signature rejection was verified over real HTTP for all three gateways
(CoinPayments, Binance, Cryptomus): unsigned and forged payloads are rejected with 400 and
logged.

## 5. Client area

Every page in the customer journey renders **200** under the `proxy` theme:

`/` · `/dashboard` · `/products/{category}` · `/products/{category}/{product}` ·
`/products/{category}/{product}/checkout` · `/cart` · `/services` · `/services/{id}` ·
`/invoices` · `/invoices/{id}` · `/tickets` · `/tickets/{id}` · `/tickets/create` ·
`/account` · `/account/security` · `/account/payment-methods` · `/account/notifications`

The service page shows the provisioned proxy details (username, password, addresses, host,
panel status) sourced from the provisioning module.

## 6. Admin area

Every daily-use screen renders **200**:

`/admin` · `/admin/provisioning-operations` · `/admin/services/services` ·
`/admin/invoice/invoices` · `/admin/tickets` · `/admin/users` ·
`/admin/extensions/extensions` · `/admin/gateway-rules` · `/admin/payment-fee-rules` ·
`/admin/canned-responses` · `/admin/ticket-notes`

The **Retry** button is present on failed, retryable provisioning rows, and the error text
is visible in the list. The Operations metrics widget is registered on the dashboard
(verified via `Filament::getPanel('admin')->getWidgets()`; its stat values load lazily over
Livewire, so they are not in the initial HTML).

## 7. Static checks

- `php -l` across all 250+ authored PHP files (extensions, themes, lang, touched core): **0 failures**.

---

## 8. Stripe test-mode setup (local)

Stripe test keys are free and instant, and do not require the client's live credentials —
create any Stripe account and use its **test** keys. Until real keys are in place every
gateway answers 401 and no deposit can complete.

1. Copy the test keys from <https://dashboard.stripe.com/test/apikeys>.
2. Paste them into **Admin → Gateways → Stripe** (`sk_test_…` and `pk_test_…`).
3. Confirm the connection:

   ```
   php scripts/verify-stripe.php
   ```

   It checks the key authenticates, that it is a *test* key and not a live one, and that a
   payment intent can be created (then cancels it). No key is ever printed.

4. The webhook is what marks the invoice paid and adds the credit. Stripe cannot reach
   `127.0.0.1`, so forward it locally:

   ```
   stripe listen --forward-to http://127.0.0.1:8080/extensions/stripe/webhook
   ```

   Paste the `whsec_…` the CLI prints into the gateway's webhook secret field.

**Leave the webhook secret field non-empty.** `Stripe::updated()` only auto-creates a
webhook endpoint on Stripe when that field is blank, and Stripe rejects local URLs — so
clearing it makes saving the gateway settings fail.

Without the webhook, a card payment succeeds at Stripe but the invoice stays *pending* and
no credit is added. That is the one link the admin "add transaction" workaround never
exercises, so it must be tested before go-live.

---

## 9. Support tickets — PDF §3 (13/13)

`php scripts/test-tickets.php` on the server:

```
[ PASS ] ticket created / department stored / priority stored
[ PASS ] service association stored              service #9
[ PASS ] customer reply recorded
[ PASS ] quick reply available and usable        "Ask for auth IP"
[ PASS ] internal note recorded
[ PASS ] internal note is not a customer message
[ PASS ] attachments supported                   file|max:10240 (10 MB)
[ PASS ] another customer cannot see this ticket
[ PASS ] email renders and reaches the mail transport
[ PASS ] ticket can be closed
```

`TicketTools` and `Notifications` were present in the codebase but **not registered on the
server** until now — so quick replies, internal notes and every notification were absent in
production while passing locally. Both are enabled there now.

Email is proven as far as it can be without credentials: one message is routed through the
`log` transport for that process only and its render confirmed. `Mail::fake()` cannot show
this — it records Mailable objects and misses a raw send entirely.

---

## 10. Domain functionality — PDF §10 (clean)

```
routes mentioning domain/whois/tld/nameserver/registrar : none
tables domains, domain_pricing, tlds, registrars        : absent
```

---

## 11. Server hygiene

The end-to-end runs create throwaway customers. After the final pass these were removed —
26 test users, 20 invoices and 3 tickets — leaving only the four real accounts, with zero
orphaned invoice items or transactions. A backup is taken before any such cleanup
(`/root/backups/pre-cleanup-*.sql.gz`).

Sender identity was corrected from the shipped default (`hello@example.com`) to
`noreply@paymenter-dev.7hoop.net`; this needs no SMTP credentials. The transport itself
still points at `127.0.0.1` and requires real mail credentials before anything can send.

---

## Not yet verified


These need something only the client can provide:

| Item | Blocked on |
|---|---|
| Binance Pay sandbox purchase end-to-end | sandbox merchant credentials + sandbox host from Binance |
| CoinPayments testnet purchase end-to-end (incl. real underpayment) | CoinPayments merchant account + LTCT testnet wallet |
| Real panel API + callback format | client's panel documentation — see `docs/modules/proxypanel.md` § Open questions |
| Client-area visual match to the reference screenshot | the reference screenshot |
| Module 4 | client's choice of gateway |

The gateway logic itself (signature verification, idempotency, underpayment handling,
invoice state transitions) is exercised by the checks above; what is missing is a real
payment flowing through a real sandbox.
