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

### 6.1 Admin sign-in and the WHMCS chrome (dev server, 2026-08-26)

Driven over real HTTP against `paymenter-dev.7hoop.net` with a throwaway administrator, which
was deleted afterwards, and screenshotted through Chrome DevTools Protocol at fixed viewports.

| Check | Result |
|---|---|
| `/admin/login` serves `…AdminOps\Admin\Auth\Login` | ✅ |
| Livewire `authenticate` → redirect to `/admin` | ✅ |
| **Two consecutive** `GET /admin` after sign-in | ✅ both 200 — this is the regression; the second used to bounce to the login form |
| Dashboard is the dashboard, not the login form | ✅ no `wire:submit="authenticate"`; rail, tiles and menus present |
| Login page at 1440 / 768 / 390 (mobile emulation) | ✅ card centred, nothing overflows, no field banding |
| Footer full-bleed, under the rail, pipe-separated | ✅ `Report a Bug \| Documentation \| Contact Us` |
| Footer is outside `.fi-layout` | ✅ last element in `<body>` |
| Topbar-end order | ✅ search · cogs (badge) · updates · wrench · account · help |
| Account menu opens | ✅ person glyph visible; **Exit Admin** and **Sign out** both present |
| Wrench menu | ✅ 14 tiles in a 3-column icon grid, inside the panel and inside the window |
| Help menu | ✅ Documentation · Technical Support · Community Forums · What's New · ─ · Version Information |

### 6.2 Top bar at width (dev server, 2026-08-26)

The bar is pinned to 45px, so anything that wraps is clipped rather than shown — a menu does
not move down, it disappears. Measured with `scrollWidth` vs `clientWidth` on
`.fi-topbar-nav-groups`, search shut and search open, at each width:

| Window | Menus fit (`scroll` = `client`) | Search open |
|---|---|---|
| 1024 | ✅ 525 = 525 | ✅ overlays, bar unchanged |
| 1120 | ✅ 525 = 525 | ✅ |
| 1200 | ✅ 525 = 525 | ✅ |
| 1280 | ✅ 721 = 721 | ✅ |
| 1366 | ✅ 721 = 721 | ✅ |
| 768 | n/a — Filament's hamburger, icons intact, no wrap | ✅ |

Before the fix: 721 vs 585 at 1024 (*Addons* off the end, no scrollbar to hint at it), and
opening the search wrapped *Addons* onto a clipped second line at every width up to ~1400.

**Not covered:** two-factor sign-in at the admin page. The code path mirrors the customer
login exactly (stash `2fa` in the session, redirect to `/2fa`, the same action finishes it),
but no administrator on this installation has `tfa_secret` set, so it has not been exercised
against a real authenticator.

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

## 12. Admin area — WHMCS usability pass (spec item 2)

Run against the development SQLite dataset (59 users, 352 invoices, 314 services, 42
tickets, 9 unresolved provisioning failures) on PHP 8.3.33, every component rendered
through Livewire as a signed-in administrator.

| Check | Result |
|---|---|
| All three widgets discovered and ordered ahead of core's | ✅ Shortcuts, At a glance, Needs attention |
| Widget figures match the same queries run independently | ✅ income $8,523.70 · outstanding $7,393.70 · 139 active |
| Action-queue rows omit zero counts | ✅ overdue + failed-payment rows absent, correctly |
| Every queue link lands on a correctly filtered list | ✅ invoices → all `pending`; services → all `suspended`; tickets → all `open` |
| Client summary renders for a customer with data | ✅ 314 services, 348 invoices, 42 tickets, credit + lifetime + outstanding |
| Client summary renders for a customer with none | ✅ empty states, "Unverified" badge |
| **Log in as customer** hidden for your own account, shown otherwise | ✅ |
| `Summary` link present on every customer row, core `Edit` intact | ✅ 10 summary links + 60 edit links on page 1 |
| Sidebar **Queues** group with live badges | ✅ Pending services (134), Suspended services (41) |
| Widget stylesheet reaches the panel `<head>` with theme variables | ✅ 5,575 bytes alongside `--color-primary` |
| Core admin pages still render | ✅ Dashboard, Users, Invoices, Services, Tickets, Products |
| Client area unaffected | ✅ Dashboard, Services, Invoices, Account, Credits, Security all render |
| Multi-currency money rendering | ✅ `$1,234.50 · R$5.678,90` — per-currency grouping honoured |
| Money edge cases | ✅ all-zero and empty collapse to `$0.00`; unknown/absent currency degrades to a plain number instead of throwing |

> **Found and fixed while verifying — three real defects, none of which a syntax check
> would have caught:**
>
> - `pluck('total', …)` on an `Invoice` aggregate returned **0**, not the SUM. Eloquent's
>   `pluck` runs a column through the model's accessors, and `Invoice::total()` recomputes
>   the figure from the invoice's items — on a model with none, that is zero. The
>   outstanding total silently read `$0.00` against 163 unpaid invoices. Aggregates are now
>   aliased `amount_sum`.
> - A widget that both `use`d `CanPoll` and redeclared its `$pollingInterval` property was a
>   **fatal** composition error on PHP 8.3. Core's widgets inherit that property rather than
>   composing the trait, which is why they get away with it. Now overridden as a method.
> - `public User $record` on the summary page was assigned the raw `{record}` string from
>   the URL — Livewire fills same-named public properties *before* `mount()` runs — and
>   threw a `TypeError` on every visit. The property is now `$customer`.
>
> **Worth knowing:** an extension **cannot** add a record action or a filter to a core
> table. `Table::configureUsing()` runs inside `Table::make()`, and the resource's `table()`
> method runs afterwards with `->recordActions([])` / `->filters([])`, both of which reset
> the array first. This is why the `Summary` link is core touchpoint #10 and why the action
> queue links to filters core already ships.

**How the thin parts of the dataset were covered:** it holds only USD, and 348 of its 352
invoices have no due date, so nothing in it is overdue and nothing spans currencies. Both
paths were exercised by inserting a second currency and back-dating invoices **inside a
rolled-back transaction**, then confirming the original figures returned afterwards. What
this does *not* prove is the behaviour over months of real multi-currency trading.

The `orders` table is also empty here (314 services exist without them), which is why the
"new orders" figure is counted from services — see `Metrics::newServices()` for why that is
the right measure in Paymenter regardless.

---

## 13. Panel API coverage and the Locations console (2026-08-25)

Run against the **live** panel (`adminproxies-dev.melodyproxy.com`) from the production
container, and against the production database. A verified backup was taken first:
`/root/backups/pre-mockdata-cleanup-2026-08-25-014258.sql.gz` (69 tables, gzip-tested).

| Check | Result |
|---|---|
| `GET /services/plans` | ✅ 200 — 9 plans |
| `GET /services/locations` | ✅ 200 — 6 in-stock names |
| `GET /locations/list` paging | ✅ 246 rows in 0.42s, 3 pages, 0 duplicates |
| `GET /locations/{tag}` | ✅ 200 with provider priorities |
| `POST /locations/new` | ✅ created `aq-pay-1` |
| `POST /locations/update/{tag}` | ✅ city changed and re-read |
| `GET /locations/status/{tag}/disabled` → `/enabled` | ✅ both, re-read confirms |
| `GET /locations/delete/{tag}` | ✅ removed |
| **Catalogue restored after the round trip** | ✅ 246 → 247 → 246, zero leftovers |
| Cross-check: sellable count vs `/services/locations` | ✅ both 6 |
| Console renders on production | ✅ 641 KB, 0.63s, real rows |
| Search / sort / filters / pagination | ✅ see below |
| Filter partition `sellable+out+empty` | ✅ 6+0+240 = 246 |
| Nav group **Panel → Locations** resolves | ✅ |
| `GET /tunnels/*` | ❌ `list` 500, per-tunnel routes 404 |
| Regenerated `paymenter-clean.sql` | ✅ 299 rows / 11 tables, structure validated |
| Export contains no test product, emails, hashes, or credentials | ✅ all zero |

Table behaviour, on live data: page 1 `ad-and-1…br-cam-1` (25 rows), page 2
`br-cur-1…ch-zur-1` (25), page 10 (21) = 246; sort by free desc gives
`dj-dji-1(256), us-los-1(3), id-jak-1(2)…`; search `kansas` → 3; search + sellable filter → 1;
continent filter `Oceania` → 4, all matching.

> **Found and fixed while verifying — four defects, none visible from reading the code:**
>
> - **The client's `locations.md` is wrong.** `linode` is rejected outright, undocumented
>   `sevencloud` is mandatory, and every provider priority is required at an exact width
>   (`do` 4, `vultr` 3, `sevencloud` 6). The form was built to the document and could not have
>   created a single location. Now built to the panel.
> - **The console rendered 5.5 MB of HTML** — Filament renders exactly what a `records()`
>   closure returns, and returning all 246 rows paginated nothing. Now returns a
>   `LengthAwarePaginator`; 641 KB.
> - **A slash in a page slug broke the sidebar.** `panel/locations` became the route name
>   `…pages.panel.locations`, which the navigation item could not resolve — the page rendered
>   but the sidebar 500'd. Slug is now one segment.
> - **The first regenerated export was corrupt.** A hand-rolled escaper mangled
>   `settings.mail_header`, a multi-line HTML document, leaving an unterminated string literal
>   that would have failed the import. Values are now quoted by the PDO driver.
>
> **Could not be verified, and why:** a real import test of the export needs a scratch
> database. The app DB user is granted `ALL ON paymenter.*` only and MariaDB `root` is not
> reachable even over TCP — correct hardening, but it means the export is validated
> structurally (every INSERT's arity and quoting parsed) rather than by importing it.

---

## 14. Client-area audit (2026-08-25)

Driven from the production logs and the live site rather than from reading templates.

| Check | Result |
|---|---|
| All 21 extensions present; 20 enabled | ✅ only DiscordNotifications off, deliberately |
| `LocalDevOverrides` enabled in production | ✅ inert — needs `APP_ENV=local` **and** `LOCAL_APP_URL`; server is `production` |
| Guest pages: `/`, `/login`, `/register`, `/announcements`, `/contact`, `/cart` | ✅ 302 to login (portal behaviour) / 200 |
| `/knowledgebase`, `/network-status` redirect guests to login | ✅ **intended** — matches the reference's "This page is restricted" |
| `/account/affiliate` behind auth | ✅ (an earlier `/affiliates` 404 was a wrong URL guess, not a defect) |
| Catalogue integrity | ✅ 34 products, 0 without a plan, 0 plans without a price, USD + BRL complete |
| Live site `/`, `/login`, `/admin` | ✅ 200 |

> **Found — the most serious defect in the project so far. Every order was failing.**
>
> ```
> [ProxyPanel] ProxyPanel create failed
> SQLSTATE[22001]: String data, right truncated: 1406 Data too long for column 'value'
> insert into `properties` (`key`, `value`, …) values (proxy_ips, [2a10:500:…]:10000, …)
> ```
>
> The module stored a service's proxies as one comma-joined string in `properties.value`,
> which is `TEXT` — about **1,213 endpoints**. Measured against the live catalogue:
>
> | Tier | Ports | Joined size | vs TEXT |
> |---|---|---|---|
> | Amethyst | 1,500 | 81 KB | **over** |
> | Emerald | 4,500 | 243 KB | **over** |
> | Jade | 13,500 | 729 KB | **over** |
> | Onyx | 22,500 | 1.2 MB | **over** |
> | Ruby | 31,500 | 1.7 MB | **26× over** |
>
> **No product in the catalogue could complete provisioning.** Worse, the panel had already
> allocated the proxies before the write failed, so every attempt leaked capacity — which is
> consistent with the successful-then-reverted services in the provisioning log.
>
> It went unnoticed because ticket-email piping is misconfigured and logs ~413 failures a day
> (see `PANEL-QUESTIONS.md` B3), burying two real errors among hundreds of noise lines.
>
> **Fixed** by `Support/Endpoints.php` + `proxypanel_endpoints`, one row per proxy.
> Verified against the failing size on the production database:
>
> | Check | Result |
> |---|---|
> | 1,500 endpoints joined = 68,998 bytes | ✅ confirms it exceeded TEXT |
> | `replace()` stores 1,500 rows | ✅ 0.06s |
> | Round-trip identical to input | ✅ |
> | `replace()` twice → still 1,500 | ✅ idempotent |
> | `all(limit)` / `each()` chunking | ✅ 100; 1,500 over 3 batches |
> | `split()` on bracketed IPv6, bare IPv6, IPv4, garbage, empty | ✅ all correct |
> | Legacy `proxy_ips` property still read when the table is empty or absent | ✅ by design |
>
> The scratch table used for that test was dropped afterwards, and no migration row was
> written, so the migration creates it cleanly on deploy.

**Deploy step — this one is required, not optional.** The migration ships in the extension and
Paymenter only runs those on enable, so on the existing install run it once:

```bash
php artisan tinker --execute="\App\Helpers\ExtensionHelper::runMigrations('extensions/Servers/ProxyPanel/database/migrations');"
```

Until it runs, everything falls back to the old property — nothing breaks, but large services
still cannot provision.

---

## 15. Production deploy of `bde6e98` (2026-08-25)

The server was **8 commits behind** — it had none of the admin work, so this deploy carried
`b007535`…`bde6e98`, not just the two most recent commits.

Backup `/root/backups/pre-deploy-2026-08-25-114306.sql.gz` (verified), rollback point
`abe1d3d` recorded at `/root/deploy-rollback-commit`. Pull was fast-forward and left the
tree clean. No dependency or config changes; three core files (`UserResource`,
`ImpersonateMiddleware`, `AdminPanelProvider`), all previously verified locally.

| Check | Result |
|---|---|
| `ExtensionHelper::runMigrations(...)` for ProxyPanel | ✅ `proxypanel_endpoints` created, migration row recorded |
| Admin panel path / login route | ✅ `admin`, `filament.admin.auth.login` registered |
| Dashboard widgets, in order | ✅ Shortcuts → AtAGlance → ActionQueue, ahead of core's four |
| Navigation groups | ✅ **Queues** (Pending services 8) and **Panel → Locations** |
| Pages render | ✅ Dashboard, ListUsers, ClientSummary, all 3 widgets, PanelLocations (641 KB) |
| `Summary` link on the customer list | ✅ 4 links, core Edit links intact (24) |
| Endpoints store | ✅ table present; legacy `proxy_ips` still readable |
| Public pages | ✅ `/`, `/login`, `/register`, `/announcements`, `/contact`, `/cart`, `/admin` all 200 |
| Post-deploy errors | ✅ none new — only the pre-existing `FetchEmails` noise |

**Not done in this session, and why:** the production mock-data cleanup. A backup was taken
(`pre-cleanup-2026-08-25-115112.sql.gz`) and the dry run completed, but cancelling the three
live panel services (`1145`, `1147`, `1148`) is an irreversible third-party call and was
blocked by a permission rule. Nothing was deleted or cancelled. See `docs/OUTSTANDING.md` 2.2.

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
