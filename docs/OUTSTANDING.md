# Outstanding problems

Everything still open, worst first. Updated 2026-08-25, after deploying `bde6e98`.

Each row says **who can move it** — that is usually the thing that decides how long it sits.

---

## P1 — Blocking delivery

### 1.1 Tunnels API does not respond · *panel developer*

`GET /v0/tunnels/list` → HTTP 500; every per-tunnel route → 404. Not authentication (a bad
token answers `200 "Unable to authorize"`, so the fault is after auth). **7 of the 26
documented endpoints are unusable**, and they are the only ones not in use.

Our side is finished: the client is written in `Support/PanelApi.php` and
`tunnelsAvailable()` probes at runtime, so the console appears with no code change the day
the panel serves them. See `PANEL-QUESTIONS.md` A1.

---

## P2 — Costing money or hiding faults today

### 2.1 Ticket email piping is misconfigured · *Leandro decides, we apply*

Fails every five minutes against `ssl://172.18.0.1:993`, where no IMAP service runs — **~413
errors a day**. Nothing is lost, but it buries real errors: this is exactly why the
provisioning failure below went unnoticed for weeks.

Three ways out, in `PANEL-QUESTIONS.md` B3. Option 1 is one command and reversible in
seconds:

```bash
php artisan app:settings:change ticket_mail_piping 0
```

### 2.2 Mock data still on production · *needs two answers, then ~10 minutes*

Blocked on a permission and two questions, not on effort. A backup is already taken
(`/root/backups/pre-cleanup-2026-08-25-115112.sql.gz`).

| Account | Verdict | Blocker |
|---|---|---|
| `you@example.com` — 1 svc, 0 inv, never signed in | remove | see below |
| `poseidonsea.dev@gmail.com` — 8 svc, 11 inv (6 paid) | remove | see below |
| `admin@example.com` — **live admin**, signed in yesterday | **rename** | need a real address |
| `netfiretecc@gmail.com` — 5 svc, 4 inv, 1 payment | ? | is it real or test? |

Two things the dry run caught that a naive cleanup would have got wrong:

- **3 services are still active on the panel** (ids `1145`, `1147`, `1148`). Deleting them
  locally orphans allocated proxies, so they must be cancelled panel-side first — and that
  call is currently blocked by a permission rule.
- **The test product cannot be deleted.** `IPv6Proxy Test` has 8 services and **3 belong to
  accounts being kept**, so removing it would break services on accounts that stay.

Removal would take: 2 users, 9 services, 11 invoices, 13 invoice items, 2 transactions,
17 properties.

---

## P3 — Worth doing, nobody is blocked

### 3.1 Admin panel still at `/admin` · *one command*

The renameable path was asked for and built, and production has never used it:

```bash
php artisan app:settings:change admin_path <new-path>
```

### 3.2 `blacklist_id` has no known source · *panel developer*

`GET /services/blacklist/{blacklist_id}/{status}` takes a blacklist id, and nothing returns
one. `setBlacklist()` is implemented but cannot be given a UI. `PANEL-QUESTIONS.md` A2.

### 3.3 `POST /services/aa/{id}` never confirmed · *panel developer*

Documented as replacing `auth_ips` + `credentials`. Deliberately untested — calling it would
rewrite a live customer's proxy authentication. No functional gap; the two-call path works.
`PANEL-QUESTIONS.md` A3.

### 3.4 Panel security · *panel developer*

Tracy debug pages served to unauthenticated callers (framework and version disclosed, stack
traces on real exceptions), and authentication failures return **HTTP 200** instead of 401 —
so a client checking only the status code reads a rejected token as success, including on
`/cancel/{id}`. `PANEL-QUESTIONS.md` A5.

### 3.5 `locations.md` is wrong · *panel developer*

`linode` is rejected, undocumented `sevencloud` is mandatory, priorities are fixed-width.
The correct contract is recorded in `docs/modules/proxypanel.md`; the client's own document
still misleads. `PANEL-QUESTIONS.md` A4.

---

## P4 — Housekeeping

### 4.1 Pre-existing Pint failures

23 files across extensions nobody has touched recently fail `pint --test`, mostly line
endings. Left alone deliberately — reformatting them would bury real diffs. Worth one
dedicated commit at a quiet moment.

### 4.2 The clean export is validated, not import-tested

`paymenter-clean.sql` is checked structurally (every INSERT's arity and quoting parsed) but
never actually imported, because the app database user is granted `ALL` on its own schema
only and MariaDB `root` is unreachable even over TCP. Correct hardening; it just means the
last mile is unproven. A scratch database on any other host would close it.

---

## Recently closed

| Problem | Closed by |
|---|---|
| **No order could provision** — proxy list exceeded `properties.value` (TEXT). Every tier from 1,500 to 31,500 ports was over; the panel allocated proxies, then the write failed and leaked them | `a57d70b`, deployed and migrated 2026-08-25 |
| Panel locations unmanageable from Paymenter | `bde6e98` — all 6 endpoints, round-trip verified |
| `paymenter-clean.sql` shipped a `$10` placeholder catalogue | `bde6e98` — regenerated from production, 33 real products |
| Production 8 commits behind | deployed 2026-08-25, verified |
