# Questions about the proxy panel API

Findings from integrating Paymenter with the panel at `adminproxies-dev.melodyproxy.com`,
tested live on **2026-08-25**. Everything below is an observed request and response, not a
guess.

Two sections: **A** needs the panel developer, **B** needs Leandro only.

## The API problems at a glance

Every panel-side issue in one table. Detail follows below.

| # | Problem | Endpoints affected | Severity | What we need |
|---|---|---|---|---|
| A1 | **Tunnels API does not respond** — `list` returns 500, per-tunnel routes return 404 | 7 | **Blocking** | Enable or fix them |
| A2 | No way to obtain a `blacklist_id` | 1 | Feature unusable | Which endpoint returns one |
| A3 | `POST /services/aa/{id}` never confirmed live | 1 | Unknown | Is it implemented? |
| A4 | `locations.md` contradicts the panel in 3 ways | 6 | Cost a rebuild | Correct the document |
| A5 | Tracy debug pages public; auth failures return HTTP 200 | all | **Security** | Disable Tracy; return 401 |

**19 of the 26 documented endpoints are in use today.** The other 7 are all A1 — the entire
Tunnels group. Nothing else is outstanding on our side.

---

# A. For whoever maintains the panel

## A1. The Tunnels API does not work — 7 endpoints unusable

`docs/client-brief/tunnels.md` documents seven endpoints. None of them respond.

```
GET /v0/tunnels/list                        → HTTP 500  (HTML error page)
GET /v0/tunnels/list?page=1                 → HTTP 500
GET /v0/tunnels/list?page=0                 → HTTP 500
GET /v0/tunnels/1/class/TunnelBroker        → HTTP 404  "Not found"
GET /v0/tunnels/info/1/class/TunnelBroker   → HTTP 404  "Not found"
GET /v0/tunnes/list?page=1                  → HTTP 404  (the spelling used in the doc)
```

**This is not an authentication problem.** With a deliberately invalid token the same URL
answers differently:

```
GET /v0/locations/list?page=1  with a bad token  → HTTP 200  "Unable to authorize your request"
```

So authentication succeeds and the failure happens afterwards. The 500 body carries no
detail — it ends with *"Tracy is unable to log error"*, so nothing was written to the panel's
own log either.

The 404s look like the routes are not registered at all, rather than the record not existing —
a missing record normally returns `{"status":"error"}`, not a router 404.

**Questions**
1. Are the tunnel endpoints supposed to be enabled on this panel?
2. If yes — can the 500 be looked at? There should be a stack trace panel-side even though
   Tracy failed to log it.
3. Is the correct path `/v0/tunnels/new` or `/v0/tunnes/new`? The doc uses both spellings.

**Why it matters:** tunnels are what give a location its capacity. Without these, Paymenter
can show that a region is out of stock but cannot show *why* or do anything about it. The
client code is already written and checks availability at runtime, so this starts working the
moment the panel does — no further development needed on our side.

---

## A2. Where does a `blacklist_id` come from?

```
GET /v0/services/blacklist/{blacklist_id}/{enabled|disabled}
```

This takes a **blacklist id**, not a service id, and no endpoint in any of the three API
documents returns one. We have implemented the call but cannot put a button on a page,
because there is no way to know which number to send.

*(To be clear: we have never called this endpoint. This is a gap in the documentation, not a
test failure.)*

**Questions**
1. What is a blacklist in the panel — is it per service, per IP, per customer?
2. Which endpoint lists blacklists, or returns the id for a given service?
3. Should customers be able to toggle this themselves, or is it admin-only?

---

## A3. Is `POST /v0/services/aa/{id}` live?

`api.md` documents it as updating **all** the assigned IPs/ports' authentication and
authorization in one call — apparently replacing this pair, which is what we use today:

```
POST /v0/services/auth_ips/{id}      {"ips": [...]}
POST /v0/services/credentials/{id}   {"username": "...", "password": "..."}
```

We use the pair because that is what the production WHMCS module does. We have **not** tested
`aa`, deliberately: calling it would rewrite a real customer's proxy authentication, which is
not something to try speculatively.

**Questions**
1. Is `/aa/{id}` implemented and live?
2. Does it fully replace `auth_ips` + `credentials`, or complement them?
3. Should we switch to it? One call is better than two, but only if it is the supported path.

---

## A4. `locations.md` does not match the panel

This one cost real time. The location form was built from the document and **could not have
created a single location** — three of its rules are wrong. Found by round-tripping a
throwaway location through create → read → update → disable → enable → delete (and removing
it afterwards; the catalogue is back to its original 246 entries).

| The document says | The panel actually does |
|---|---|
| `linode` is a mandatory provider block | **Rejects it outright**: `Unable to validate your request: Unexpected item 'linode'` |
| — no mention of `sevencloud` — | **`sevencloud` is mandatory**: `The mandatory item 'sevencloud › prio1' is missing` |
| priorities are just "array with 3 priority locations" | **All three are required, at an exact character width**: `The length of item 'do › prio2' expects to be in range 4..4, 0 bytes given` |

The widths, taken from live rows:

| Provider | Width | Example |
|---|---|---|
| `do` | 4 | `nyc1`, `sfo3` |
| `vultr` | 3 | `ewr`, `dfw` |
| `sevencloud` | 6 | `mci-00` |

Two more differences worth writing down:

- **The tag is generated by the panel**, from country + city — creating `Antarctica` /
  `Paymenter Roundtrip` produced the tag `aq-pay-1`. It cannot be supplied or changed by the
  caller. The document does not say this.
- **`total_pages` is wrong.** `GET /v0/locations/list` reports `total_pages: 2` for 246
  locations at 100 per page — but page 3 does return the missing 46. Anyone trusting
  `total_pages` silently loses a fifth of the catalogue. We page on `total` /
  `items_per_page` instead.
- The delete endpoint is listed as `/v0/locations/delete/:location_id` in the endpoint list
  but `:location_tag` in the detail section. **The tag is what works.**

**Question:** can `locations.md` be corrected? Anyone else integrating from it will hit
exactly the same wall.

---

## A5. Two security issues visible from outside the panel

Noticed while testing, unrelated to our integration but worth fixing.

**1. Debug pages are served in production.** Any error returns a Tracy debug page to an
unauthenticated caller. A simple 404 came back containing:

```
<!-- Tracy Debug Bar -->
<script src="/v0/tunnels/list?..&_tracy_bar=js&v=2.10.5&XDEBUG_SESSION_STOP=1">
```

That discloses the framework, the debug-bar version, and — on a real exception — file paths
and a stack trace. Tracy should be disabled in production, or restricted to a trusted IP.

**2. Authentication failures return HTTP 200.** A wrong token gets:

```
HTTP 200
Unable to authorize your request      (plain text, not JSON)
```

The conventional answer is `401` with a JSON body. As it stands, any client that checks the
HTTP status code — which is the normal thing to do — reads a **rejected token as success**.
That is dangerous on the destructive calls: a misconfigured token would make
`GET /v0/services/cancel/{id}` look like it worked.

*(Our module handles both cases, because we found them. A future integrator may not.)*

---

## What we can do while we wait

None of the above blocks the rest of the work. For each, this is what is already in place:

| Problem | Our side, already done | What only the panel can do |
|---|---|---|
| **A1 Tunnels** | The full client is written (`Support/PanelApi.php`) and `tunnelsAvailable()` probes at runtime, so the console appears with no code change once the endpoints answer | Serve them |
| **A2 Blacklist** | `setBlacklist()` is implemented and tested against the documented contract | Say where an id comes from |
| **A3 `aa`** | The two-call path (`auth_ips` + `credentials`) works and is what production has always used — there is no functional gap, only a possible simplification | Confirm whether to switch |
| **A4 `locations.md`** | The console is built to the panel's real contract, and the correct rules are written down in `docs/modules/proxypanel.md` so nobody has to rediscover them | Fix the source document |
| **A5 Security** | Our client treats a `200 "Unable to authorize"` as a failure and never pastes an HTML error page into the UI | Disable Tracy, return 401 |

So the only thing genuinely waiting on the panel is **A1**.

---

# B. For Leandro

## B1. Is `netfiretecc@gmail.com` a real customer or a test account?

On the Paymenter server this account has **5 services, 4 invoices (2 paid) and 1 payment
transaction**. It is your own email address, so we have not touched it.

- If it is **test data**, we will remove it with the rest.
- If it is **real**, it stays.

Either way, one thing to note: deleting a service in Paymenter does **not** cancel it on the
proxy panel. Anything we remove should be cancelled panel-side first, or the panel keeps the
tunnels allocated to a service that no longer exists.

## B2. What real email should the admin account use?

There are two administrator accounts, both on placeholder addresses:

| Account | Last sign-in | What to do |
|---|---|---|
| `admin@example.com` | **2026-08-24** — actively in use | **Rename it.** Deleting it would lock you out. |
| `you@example.com` | never | Safe to delete |

Please send the real address for the first one.

## B3. Ticket email piping is switched on but has nothing to connect to

The scheduler tries to read a mailbox every five minutes and fails every time:

```
FetchEmails failed: Unable to connect to ssl://172.18.0.1:993
```

**413 failures in a single day.** Nothing is lost — no email was ever imported — but it fills
the log and buries real errors, which is how the provisioning bug below went unnoticed.

The settings point at the server's own SMTP host on the IMAP port, and no IMAP service runs
there. Three ways out, in increasing order of effort:

1. **Turn it off** until there is a mailbox to read — one command, stops the noise today:
   ```bash
   php artisan app:settings:change ticket_mail_piping 0
   ```
2. **Point it at a real mailbox** — if `support@` is hosted somewhere with IMAP, give us the
   host, port and credentials and it starts working.
3. **Run IMAP on the server** (Dovecot alongside the existing Postfix), if you would rather
   the mailbox lived with everything else.

Which do you want? Option 1 is reversible in seconds, so it is the sensible default until 2
or 3 is decided.

## B4. Should the admin panel move off `/admin`?

You asked for the WHMCS behaviour where the administrative area lives at a directory that can
be renamed, so it is harder to find. That is built and working, but production is still on the
default `/admin`. Changing it is one command and takes effect immediately:

```bash
php artisan app:settings:change admin_path <new-path>
```

Tell us the path you want, or say to leave it.
