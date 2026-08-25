# Administrative Area Customization (spec item 2)

The brief asks for an admin area **optimized for daily operations**, with improved
organization of clients / products / services / payments / tickets and an
**administrative dashboard with operational metrics**. The client later pointed at the
WHMCS admin as the reference: *"this is the expected admin area design, or the closest
thing to it — Paymenter's current design lacks good usability."*

This document covers what was built to close that gap. The **access rules** for the admin
area (separate login, renameable path, staff kept out of the client area, impersonation)
are a different piece of work — see the "Admin panel: own login, renameable path" entry in
[`CORE-TOUCHPOINTS.md`](CORE-TOUCHPOINTS.md) and `docs/12-security.md`.

## What Paymenter's admin already gives you

Paymenter's admin is a modern **Filament 5** panel, not a legacy table UI, and most of the
raw material is already there:

- **Organized navigation groups** — Administration, Configuration, Extensions, System.
- **Rich resources** — searchable, filterable tables with bulk actions and a policy per
  core model; global search on ⌘K / Ctrl-K.
- **Some counts already in the sidebar** — Invoices carries the unpaid count, Tickets the
  open count, and the ticket list opens on the Open tab by default.
- **Dashboard widgets** — Revenue, Support, Active users, Cron status.

So this was never about rebuilding the admin. It is about the gap between *"every record is
reachable"* and *"I can run the business from this screen"*.

## What was missing, and what was built

Paymenter's admin is organised around **records**; WHMCS's is organised around a **working
day**. Three concrete differences, all delivered by `extensions/Others/AdminOps`:

### 1. A homepage that says what to do today

Core's dashboard answers *"how does this month compare with last month"* — a question you
ask occasionally. WHMCS's homepage answers *"how are we doing today, and what is waiting for
me"*. Five widgets now sit above core's, in this order:

| Widget | What it is |
|---|---|
| **Headline tiles** | The reference's four coloured tiles — Pending Orders, Tickets Waiting, Pending Cancellations, Pending Module Actions. Each links into the list behind it. |
| **Shortcuts** | New customer · New service · New invoice · Open ticket · Find a customer. Each hidden unless the signed-in role may actually create that record. |
| **At a glance** | WHMCS's Overview panel — Income, New services, New customers, Tickets opened, each for Today / This month / This year / All time. Subheading carries active services and total outstanding. |
| **Needs attention** | The work queue: provisioning failures, tickets awaiting reply, services awaiting provisioning, suspended services, failed payments (7d), unpaid invoices, renewals due. Every line is a link straight into the matching filtered list. |
| **Who is around** | WHMCS's Staff Online and Client Activity panels: colleagues seen in the last 15 minutes, customers with a running service, customers signed in within the hour. |

**Needs attention** is the one that matters. Rows with a count of zero are **omitted**
rather than shown as zeroes, so an empty queue reads as "nothing to do" at a glance, and
rows are ordered by how much it costs to ignore them — a failed provisioning run (the
customer paid and got nothing) above an unanswered ticket, above money owed.

The tiles overlap it on purpose. A tile row is a **gauge**: fixed set, fixed position,
zeroes shown as zeroes, so you learn where "tickets waiting" lives and read it without
looking. The queue is a **to-do list**: variable length, ordered by urgency, empty rows
dropped, and it carries measures the four tiles have no room for. "Pending Module Actions"
is our provisioning failure count — WHMCS means the same thing by it, a server module call
that did not complete and is waiting on a human — and the tile is omitted entirely when
`Others/ProvisioningOps` is not installed, because a permanent zero would claim nothing is
wrong when in fact nothing is being watched.

**Who is around** reads `user_sessions`, which core's session middleware already stamps
with `last_activity` at most once a minute; the 15-minute window is deliberately much wider
than that stamp interval, or a colleague reading a page would blink out of the list between
writes. Two devices signed in as the same person count once.

### 2. A client summary — one customer, one screen

Everything on it was already in Paymenter, spread over six sub-pages (profile, services,
invoices, credits, tickets, billing agreements). Answering *"who is this and what is going
on with them"* — the first thing support does on every ticket — cost five page loads.

**Clients → Summary** (a link on every row of the customer list) now shows, on one page:

- ID, email and verification state, registration date, account credit, lifetime paid,
  outstanding — plus any custom properties (CPF/CNPJ from `Others/BrazilianRegistration`).
- The latest 8 services, invoices and tickets, each row linking to the record, each block
  showing the full count and a **See all** link to the core sub-page.
- Header actions: **Log in as customer**, **Edit customer**, **New invoice**.

**Log in as customer** is WHMCS's headline support feature and the client asked for it
explicitly. It uses core's existing impersonation — the same session key and the same
landing page as the Impersonate button on the user edit page — so there is one mechanism,
not two, and `ImpersonateMiddleware` governs both.

The page is deliberately **read-only**. Everything editable stays on the core page that
owns it, so there is one place a change can be made and one set of validation rules.

### 3. The WHMCS layout itself

The client asked for the reference's **design**, not only its information, so the panel now
wears it: a blue menu bar across the top, the left rail, bordered panels with grey heading
strips, dark table headers, square buttons, a footer.

| Piece | Where |
|---|---|
| **Menu bar** — Clients · Orders · Billing · Support · Reports & Logs · Setup · Addons | `Support/WhmcsNavigation.php` |
| **Left rail** — Shortcuts, System Information, Advanced Search, Staff Online, and the *Minimise Sidebar* bar | `Support/Rail.php` + `resources/views/rail.blade.php` |
| **Skin** — topbar, dropdowns, panels, tables, buttons, inputs, tiles | `resources/views/skin.blade.php` |
| **Footer** | `resources/views/footer.blade.php` |

All of it is plain CSS injected at `panels::head.end` and markup injected at
`panels::layout.start` / `panels::footer`. The admin theme only scans `app/Admin` and
`resources/views` for classes — `extensions/` is not a `@source` — so a utility class written
here would not exist in the compiled stylesheet; and a rebuild would put a Vite build in the
deployment path, which the server does not have. Plain CSS has neither problem.

**One core line** was unavoidable: `->topNavigation()`, recorded as
[`CORE-TOUCHPOINTS.md`](CORE-TOUCHPOINTS.md) #11. It is read while the panel is constructed,
so it cannot be set from an extension. Everything else registers afterwards.

#### What it restyles, and what it cannot

The skin restyles **Filament's own markup**. That is the trade: the panel keeps every
resource, policy, form and table it already has, and those internals stay Filament's HTML.
They read as the reference design; they are not its markup. Making them byte-identical to
WHMCS's Bootstrap 3 template would mean replacing Filament, and with it every screen in the
admin.

#### The catch-all is load-bearing

`Panel::navigation()` takes navigation over completely, so anything the menus do not name
becomes unreachable — including every resource a future extension registers. `addons()`
therefore runs last, diffs the panel's actual resources and pages against what the menus
claimed, and puts the remainder under **Addons**. Installing an extension still works
without editing this file; its screens simply land in Addons until somebody files them.

Sidebar **Queues** were removed in the same change — not dropped, moved. A NavigationBuilder
ignores `navigationItems()`, so they could not have survived; pending, suspended and
cancelled now sit in the **Orders** and **Clients** menus with their badges, and again in the
rail's Advanced Search with their counts.

A cancellation counts as pending while the service it names has not reached `cancelled` —
the row itself carries no status, because it is a request. An end-of-period request
therefore stays in the count for the rest of the term, which is right: it is work that has
not happened yet.

## Money and multiple currencies

Paymenter stores a price per currency and **no exchange rate**, so there is nothing to
convert with. Totals are therefore **never summed across currencies** — a store selling in
USD and BRL would otherwise show a number that is neither. Where a figure spans currencies
it is rendered as `$1,234.00 · R$5,678.00`. A single-currency store, which is the normal
case, sees exactly what WHMCS shows.

Income is read from **transactions**, not invoices: an invoice's total is computed from its
items in PHP and cannot be summed in SQL, and the date an invoice was paid is not stored —
the transaction's is. Credit transactions are excluded, because settling an invoice from
account credit spends money that was already counted as income when the credit was bought.

## Branding — settings-driven, no code

The admin panel reads branding from **Admin → Settings**:

| Setting | Effect on admin |
|---|---|
| **Logo** (`logo`) | Admin brand logo (light) |
| **Dark logo** (`logo_dark`) | Admin brand logo (dark mode) |
| **Favicon** | Browser tab icon |
| **App name** | Brand name shown when no logo is set |

Upload the logo and set the app name once and the panel picks them up — no code changes.
The widgets added here take their colours from the same custom properties the active theme
publishes into the panel `<head>`, so they follow the store palette and dark mode without
being told about either.

## Enable

1. **Admin → Extensions → AdminOps** (already enabled on the server).
2. Extension **widgets** are discovered by one line in `AdminPanelProvider` —
   [`CORE-TOUCHPOINTS.md`](CORE-TOUCHPOINTS.md) #3, already applied.
3. The **Summary** link on the customer list is a second one-line touchpoint —
   [`CORE-TOUCHPOINTS.md`](CORE-TOUCHPOINTS.md) #10, already applied.

Disabling the extension returns the panel to stock Paymenter; both touchpoints are guarded
by `class_exists`, so core keeps working if AdminOps is removed entirely.

### Why the Summary link needs a core touch

It was tried from the extension first. `Table::configureUsing()` is Filament's supported way
to reach a table you do not own, but it runs inside `Table::make()` — and the resource's own
`table()` method runs *afterwards* with `->recordActions([...])`, which **resets** the array
before repopulating it. Anything an extension pushes is discarded before the table renders.
`->filters([...])` behaves the same way, which is why the action queue links to filters core
already has rather than adding its own.

## Daily-operations tips

- **Assign tickets** to staff and use **Canned Responses** + **Internal Notes**
  (`Others/TicketTools`) for faster support.
- **Payment Fee Rules** and **Gateway Rules** live under **Configuration**.
- Provisioning actions (suspend/unsuspend/terminate/sync) are on each **Service**;
  failures and their **Retry** button are under **Services → Provisioning**
  (`Others/ProvisioningOps`).
