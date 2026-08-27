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

#### The panels move, as the reference's do

Leandro's note — *"All items on the WHMCS admin home screen can be moved using
drag-and-drop"* — is the whole of this. The reference gives every homepage panel four
behaviours, and the dashboard now has the same four, remembered in the same places:

| Behaviour | Reference | Here | Remembered |
|---|---|---|---|
| **Drag** by the panel heading | Packery + Draggabilly, saved to `/widget/order` | HTML5 drag, saved on drop | **Per admin**, in the database |
| **Hide** (×), and the settings menu that brings it back | `/widget/display/toggle/{id}` | Same toggle, both routes to it | **Per admin**, in the database |
| **Collapse** (▲) | `localStorage.minimisedWidgets` | `localStorage.aoCollapsedWidgets` | **Per browser** |
| **Refresh** (⟳) | `/widget/refresh` re-renders the body | The widget's own Livewire `$refresh` | — |

Collapsing is per browser in both, and deliberately: it is a "how am I reading this screen
right now" decision, not a preference that should follow you to another machine.

Two panels do not move, matching the reference's *stamped* static widget: the **tile row**,
which is a gauge whose value is that the same figure is always in the same place, and the
tools widget itself, which draws nothing.

A widget is identified by its **Livewire component name**. Filament renders each widget as
its own Livewire component and derives that name from the class, so it is the same string on
every page load and on every machine — which the DOM id, the render key and the position are
not. A saved name that no longer matches a widget is ignored, so uninstalling an extension
needs no cleanup.

**Why a widget rather than a replacement dashboard page.** Ordering the widgets in PHP would
be simpler to reason about, but telling the panel which class its dashboard is happens while
the panel is constructed — a core edit, and a twelfth touchpoint on the one screen every
admin lands on. `Admin/Widgets/DashboardTools.php` sorts first, renders no panel, and carries
the script and the two methods it calls. Disable AdminOps and the dashboard is stock Filament
again.

The cost is honest and small: the saved order is applied after the widgets have painted, so a
rearranged dashboard settles into place rather than arriving in place. **Nothing is hidden
while that happens** — a dashboard left blank by a script that failed is far worse than one
that reshuffles once. The same reasoning is why `DashboardTools::canView()` checks its table
exists: an enabled-but-unmigrated install falls back to the stock dashboard instead of
fatalling on the panel's home page.

Headings are focusable and take the left/right arrow keys, so the dashboard can be
rearranged without a mouse — and on a touch screen, where the HTML5 drag API does nothing.

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
| **Toolbar icons** — the `+` at the start of the bar, and automation status / updates / setup / account / help at its end | `Support/Toolbar.php` + `resources/views/quick-create.blade.php`, `toolbar.blade.php` |
| **Left rail** — Shortcuts, System Information, Advanced Search, Staff Online, and the *Minimise Sidebar* bar | `Support/Rail.php` + `resources/views/rail.blade.php` |
| **Skin** — topbar, dropdowns, panels, tables, pagination, forms, stat tiles, buttons, inputs | `resources/views/skin.blade.php` |
| **Footer** | `resources/views/footer.blade.php` |
| **Sign-in page** — Paymenter's auth stack, 2FA, and the CAPTCHA (§4) | `Admin/Auth/Login.php` + `resources/views/captcha.blade.php` |
| **Products/Services** — the drag-ordered catalogue (§5) | `Admin/Pages/Catalogue.php` + `resources/views/pages/catalogue*.blade.php` |

#### The icons at each end of the bar

The reference bar is not only menus. It opens with a **+** that creates any record from
wherever you happen to be, and closes with six icons — search, automation status, updates,
setup, account, help — which is where you go when the thing you want is not a customer record.

Which of them are *menus* and which are plain links was read off the reference's own template
(`admin/templates/blend/nav.tpl`, `ul.right-nav`), because that difference is not visible in a
screenshot. Two of them are links.

- **`+`** — new client, order, invoice, service, ticket, product. Injected at
  `panels::topbar.logo.after`, so it sits between the brand and the menus.
- **Search** — Filament's own global search, which was already in that slot. The skin
  collapses it to the reference's bare magnifier and grows it into a field on focus, so
  core's resource-aware results and its ⌘K binding are kept rather than reimplemented.
- **Automation status** — a **link** to the cron page, carrying the reference's red badge.
  The badge counts failed jobs plus exceptions from the last seven days: `debug_logs` is
  never pruned, so an unwindowed badge would only ever grow. The logs it used to list are all
  in the **Utilities** menu, where the reference keeps them.
- **Updates** — a **link** to the updater.
- **Setup** — a menu, laid out as the reference's grid of icon tiles (`ul.drop-icons`). It
  carries the full setup list rather than the reference's six, because WHMCS's wrench opens
  onto a Setup *index page* that holds the rest and Paymenter has no equivalent — and the bar
  itself has no Setup menu, to match the reference.
- **Account** — Filament's own user menu, which is where **Sign out** lives. Not rebuilt
  here; the skin moves it into the reference's position (after the wrench, before the
  question mark) by ordering the flex row, so core's working sign-out and theme switcher are
  kept rather than duplicated.
- **Help** — documentation, technical support, community, what's new, and, below a rule,
  version information: the reference's five entries in the reference's order.

Every entry is permission-checked and its URL is resolved while the list is built, for the
reason at `WhmcsNavigation::resolveUrl()`: this renders on **every** admin page, so a link
that cannot be built has to disappear rather than throw mid-topbar. A cluster whose entries
are all filtered out is dropped, not shown empty.

Two things about the account menu are worth knowing, because both looked like missing
features rather than defects:

- **The avatar never painted.** Paymenter's avatar provider returns a **Gravatar URL**, and
  the trigger button is nothing but that image — so on a server that cannot reach
  gravatar.com, or for an address with no Gravatar, the button collapsed to nothing and the
  panel appeared to have no sign-out at all. The skin hides the remote image and draws a
  person glyph from an inline `mask-image` instead: no request, nothing to fail.
- **The setup grid overflowed its own panel.** `.fi-dropdown-panel` carries
  `max-width: 14rem !important` in the compiled theme, which no rule in `skin.blade.php` can
  outrank, so the tiles rendered outside the white panel and off the edge of the window. The
  panel is widened through Filament's own `width` prop, which *is* written to beat it, and
  the dropdown is given `shift` so floating-ui keeps it on screen.

The menu labels themselves lost their icons, because the reference's are text only. The
group icons are still set — the rail now reads them for its section heading, so the icon
beside *Support* in the rail is by construction the same one the menu means.

#### The bar is one line high, and that has consequences

Filament's topbar grows with its contents: `.fi-topbar-nav-groups` carries `flex-wrap: wrap`,
so when the menus stop fitting they take a second line and the bar gets taller. This skin pins
the bar to 45px to match the reference, which turns that graceful behaviour into a silent
fault — the second line is **clipped**, so a menu does not move, it disappears.

It was reachable two ways, and both are closed:

- **Opening the search.** Core's `.fi-global-search` is `flex: 1`, and the skin grew the input
  to 14rem on focus. That 14rem came out of the menus' width, which pushed *Addons* onto the
  clipped second line: searching made a menu vanish. The expanded field is now an **overlay** —
  absolutely positioned off the right-hand end of its own cell — so the row cannot change shape
  no matter how wide the field is. Its results panel is re-anchored to match, because core
  stretches that to `inset-inline: 1rem` of a search that is no longer full-width.
- **A narrow window.** Measured on the dev panel: brand 152px + menus 721px + icons 249px =
  1122px, and Filament only takes over with the hamburger below 1024px — so there was a 100px
  band where the row overflowed. `flex-wrap: nowrap` stops the clipping, and two media queries
  give up ornament rather than content: tighter cells at 1400px, and at 1200px the dropdown
  chevrons (~20px × 7) and some icon padding. That is about 200px, which carries all seven
  menus down to the width where core hides them anyway. Verified `scrollWidth === clientWidth`
  at 1024, 1120, 1200, 1280 and 1366.

Letting the row scroll sideways is the last resort rather than the fix, and it is only safe at
all because Filament teleports every topbar dropdown to `<body>` — the panels are not children
of the scrolling element, so clipping the row does not clip the menus.

#### When `support.js` does not load, every menu opens at once

Worth knowing because it looks nothing like its cause. If
`js/filament/support/support.js` fails to execute, `Alpine.data('filamentDropdown')` is never
registered. Alpine still boots — Livewire bundles it — so it still strips `x-cloak` from every
dropdown panel, then throws `filamentDropdown is not defined` on each one and never positions
any of them. The result is **all eleven panels in the bar open simultaneously**, stacked over
the page. It reads as "the CSS has completely broken", which sends you looking in the wrong
place; the stylesheets are fine and one script is missing.

Diagnosed by blocking that single URL in DevTools, which reproduces the reported screenshot
pixel for pixel. Blocking `app.js` instead breaks only the sidebar store; blocking
`livewire.min.js` leaves every panel `x-cloak`ed and therefore correctly hidden.

The skin now hides any panel that has no inline `display` (`skin.blade.php`, the rule above
`.fi-dropdown-panel`). Filament's `x-float` always writes one — `display: none` when closed,
`display: block; left: …; top: …` when open — so its absence means nothing has taken charge of
that panel, and hidden is the right default. Verified: with the script blocked, 0 of 11 panels
are visible and the page stays usable; with it loading, all three dropdown kinds (topbar menu,
wrench grid, account) still open exactly one panel each.

The likely trigger in the wild is caching. That file is 142 KB, served through Cloudflare with
`Cache-Control: max-age=14400` and a `?v=5.6.8.0` buster tied to the *Filament version*, not to
the deploy — so a truncated or errored copy, once cached, persists for up to four hours and a
plain reload will not shift it. A hard reload (Ctrl+Shift+R) clears it.

The **footer** is the reference's split bar: copyright at the start, pipe-separated links at
the end. It renders at `panels::body.end` rather than the obvious `panels::footer`, for two
reasons. `panels::footer` fires inside `.fi-main-ctn` — the content column, whose start edge
is the left rail — while the reference's bar runs the full width of the window and passes
*under* the rail; and on the sign-in page, whose layout is a centred column, it produced a
short blue bar floating under the login card. `body.end` is outside `.fi-layout` entirely, so
the bar is full-bleed by construction. `.fi-main-ctn`'s `min-height` is re-stated to
`100dvh − topbar − footer`, or every page would carry a scrollbar with nothing under the fold
but the footer.

All of it is plain CSS injected at `panels::head.end` and markup injected at
`panels::layout.start` / `panels::body.end`. The admin theme only scans `app/Admin` and
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

### 4. The sign-in page, and its CAPTCHA

The panel signs staff in through AdminOps' own login page rather than Filament's, for a
reason that has nothing to do with looks: Paymenter treats the `user_sessions` row as the
authority, and Filament's page never creates one, so the stock page authenticated and was
bounced straight back to the form. That page is also where Paymenter's own `tfa_secret` is
enforced, which Filament's separate multi-factor system knows nothing about.

Being our page, it can carry the client login's **CAPTCHA** — and now does. The client asked
for the two to match, and they do in the strongest sense available: one setting
(**Settings → Security**), one site key and secret, one server-side verification
(`App\Traits\Captchable`). Configure the storefront and the admin door is protected by the
same act.

What is *not* shared is the widget markup. The client's `<x-captcha>` is a theme component
styled with the portal's `wf-` classes, and the admin panel does not load the theme, so
`adminops::captcha` renders into Filament's sign-in card instead. It supports the same four
providers the setting offers — reCAPTCHA v2 and v3, Turnstile, hCaptcha — and repeats the
three behaviours the theme's version had to learn:

- **`wire:ignore` around the widget.** A Livewire morph that replaced the iframe would
  silently discard a solved challenge.
- **Reset after a request that spent a token**, clearing it on the component at the same
  time. Miss either half and the next submit replays a token the provider has already
  burned, which comes back "invalid" — so a wrong password becomes an unexplained CAPTCHA
  failure. Because that means solving it again, the page says so rather than returning a
  blank checkbox and, one attempt later, "The CAPTCHA is required."
- **Expiry and error clear the token**, so a challenge left for two minutes reports the
  accurate "required" instead of submitting a dead one.

Two things it adds that the client login does not need. The panel runs `->spa()`, so the
sign-in page can arrive by `wire:navigate` — same JS context, new DOM, new component id —
which is why the widget is held in a registry keyed by component id and nothing in the
script closes over an element. And the token is verified **after** the rate limiter rather
than before it: an unanswered challenge should still spend one of the five attempts, or a
bot that never solves one gets unlimited free guesses at the password.

It also cannot lock anybody out. The challenge is enforced only when a provider **and** both
keys are set, because `Captchable` fails any submission without a token and no site key means
no widget can produce one — enabling the setting with a key missing would otherwise leave an
unsatisfiable form on the one page you need in order to fix it. See `docs/12-security.md` for
the escape hatch if the keys are wrong rather than absent.

The panel registers no password-reset page, so the sign-in is the only unauthenticated screen
in the admin area and there is nothing else to protect.

### 5. Products/Services — the catalogue, ordered by dragging

**Setup → Products/Services.** Every group, its products underneath, a handle on both. It is
the reference's `configproducts.php`, and it is the only screen in the panel whose purpose is
the *order* of things.

Paymenter could already order both — `categories.sort` and `products.sort` are real columns
that `App\Livewire\Products\Index` reads, so this is not a new capability — but the two halves
were on different screens and one of them was three clicks deep:

| To reorder | Before | Now |
|---|---|---|
| Groups | Categories list — draggable | Same page as the products |
| Products in a group | Categories → *edit that category* → Products tab | Same page |
| Products, from the product list | **Not possible.** It groups by category and has no handle | — |

So "put the Monthly plans above the Daily ones" meant editing each category in turn, with
nowhere to see the resulting shape. This is that shape.

Nothing else on the page is editable. Every row links to the core screen that owns it, and
the page writes exactly two columns — the same rule as the client summary, for the same
reason: one place a change can be made, one set of validation rules.

**A drag reorders within its own list.** Products stay in their category, groups under their
parent. Moving a product to another group changes `category_id`, and with it the storefront
URL, the breadcrumb and any link a customer has saved — so it stays on the product's edit page
where it is deliberate and audited. The reference behaves the same way.

**A save renumbers the whole list, 1..n**, not just the row that moved. `sort` is nullable and
every row in this store still holds `NULL`, so a partial write would leave a mix of ordered
and unordered rows whose relative order MySQL decides. The first drag on a list ends that. It
is also why the page orders by `sort` exactly as core does — NULLs first, MySQL's behaviour,
not a tidier order of its own — because a page that showed a different order from the
storefront would be reordering something the customer never sees.

The ids come from the browser, so **the list is checked against the category before anything
is written**: a set that does not match exactly is refused whole rather than partially applied.
Without that, a crafted request could renumber rows in a group the sender cannot see. A list
longer than 255 is refused too — `sort` is an `unsignedTinyInteger`, and writing anyway would
clamp the tail and scramble it.

Three smaller things worth knowing, because each was a bug before it was a decision:

- **The handle, not the row, starts a drag.** Left permanently draggable, every click on a
  product name became a drag instead of a link.
- **The rows carry `wire:key`.** A drag reorders the DOM before the server knows anything, so
  the re-render that follows is morphing new HTML onto a list in a different order than it was
  sent in. Keyed, Livewire moves rows; unkeyed, it rewrites each row's contents in place,
  which looks like the drag half-worked.
- **The save hangs off `dragend`, not `drop`.** Chrome fires no `drop` unless `dragover` was
  cancelled over the exact element released on; `dragend` always runs.

Handles are focusable and respond to the arrow keys, so the page works without a mouse — and
on a touch screen, where the HTML5 drag API does nothing at all. Reordering is gated on
`admin.products.update` / `admin.categories.update` separately, so a role that may order
groups but not products gets handles on one and not the other.

Columns are the reference's, minus one: **Auto Setup** has no Paymenter equivalent — core
provisions on payment, always — so it is left out rather than invented. **Type** is the server
the product provisions through, which is what a WHMCS module is here.

### 6. Automation Status

**Utilities → Automation Status.** The reference's `automationstatus.php` answers one
question before any other — *is this thing running at all* — and shows task activity
underneath. Core's Cron Statistics page shows the activity but not the health: it draws a
tidy row of zeroes for a scheduler that has been dead a week, because a task that never ran
records nothing, and nothing is exactly what zero looks like.

That stopped being cosmetic when `Others/TermLimits` arrived. Fixed-term services are ended
by a sweep that runs **every minute** — if the scheduler is down, daily proxies go on
working past their term and nothing else anywhere says so.

Two independent clocks, because they fail differently and the fixes differ:

| Clock | Written by | What a stale one means |
|---|---|---|
| `last_scheduler_run` | core's heartbeat, every minute | `schedule:run` is not in cron at all |
| `last_cron_run` | `app:cron-job` on completion | scheduler fine, the daily job itself is failing |

The layout is the reference's, taken from a screenshot of its own page: three tiles across
the top — verdict, last daily run, next daily run — then **Daily Actions**, a tile per task
carrying what it did today and, in red beside it, **what failed**.

That failed count is the part most worth copying. A task can half-work — one panel that will
not answer while the other fifty services end fine — and a grid showing only successes calls
that a good day. Both of our tasks now count their failures rather than throwing on the first
one, which is also why one unreachable panel no longer stops the queue.

**Fixed Term Terminations** and **Overdue Terminations** are separate tiles, as they are on
the reference, and deliberately: one is a service whose paid period ran out unpaid, the other
was always going to end on a date. Merged, a fixed-term module that had stopped would hide
behind an overdue ladder that had not.

A task that has never recorded anything gets no tile at all — so an uninstalled module leaves
no permanent zero claiming to be watched. Every task writes to `cron_stats`, the table core's
own tasks use, so this page needs no knowledge of which extension owns which tile.

Counts are shown for today *and* the last seven days: the daily job runs once at a configured
hour, so every one of today's figures is legitimately zero until it has, and a page showing
only today would report a healthy install as idle every morning. The two tasks that do not
wait for it — fixed terms every minute, cancellations hourly — say so on the page.

Core's Cron Statistics page is untouched and still linked, from the header and from
Utilities.

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
