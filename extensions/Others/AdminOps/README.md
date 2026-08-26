# Admin Operations

WHMCS-style usability for the Paymenter admin panel (spec item 2).

**Dashboard** — three widgets above core's:

- **Shortcuts** — new customer / service / invoice / ticket, and find a customer.
- **At a glance** — income, new services, new customers and tickets opened, for
  today / this month / this year, plus active services and total outstanding.
- **Needs attention** — the work queue: provisioning failures, tickets awaiting reply,
  services awaiting provisioning, suspended services, failed payments, unpaid invoices.
  Zero-count rows are omitted; every row links to the matching filtered list.

**Client Summary** — one customer on one screen: profile, credit, lifetime paid,
outstanding, custom properties, and their latest services, invoices and tickets — with
**Log in as customer** (core impersonation), Edit and New invoice in the header. Reached
from the **Summary** link on each row of Clients.

**Sidebar** — a **Queues** group with Pending services and Suspended services, badged with
live counts. (Invoices and Tickets already carry counts in core, so they are not repeated.)

**Products/Services** (Setup → Products/Services) — the whole catalogue on one screen, with a
drag handle on every group and every product, writing `categories.sort` and `products.sort`
(the columns the storefront already reads). A drag reorders within its own list; moving a
product between groups stays on the product's edit page. Handles also respond to the arrow
keys, so it works without a mouse and on touch.

**Sign-in** — the panel's own login page, on Paymenter's auth stack (so the session row is
created and 2FA is enforced), carrying the **same CAPTCHA as the client login**: one
provider, one key pair, one verification, set in **Settings → Security**. It is enforced
only when the provider and both keys are present, so a half-configured setting cannot lock
you out of the panel.

Money is never summed across currencies — Paymenter stores no exchange rate — so a
multi-currency figure renders as `$1,234.00 · R$5,678.00`.

Full documentation: [`docs/02b-admin-area.md`](../../../docs/02b-admin-area.md).

**Enable:** Admin → Extensions → AdminOps. Two one-line core touchpoints are already
applied and are what let the widgets and the Summary link appear — see
[`docs/CORE-TOUCHPOINTS.md`](../../../docs/CORE-TOUCHPOINTS.md) #3 and #10. Admin branding
(logo / name / favicon) is settings-driven and needs no code.
