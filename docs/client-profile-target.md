# Client Profile — the reference, tab by tab

Written from Leandro's WHMCS screenshots of `clientssummary.php?userid=1` (2026-09-08),
covering every tab. Our page is `/admin/client-summary/{id}`
({@see extensions/Others/AdminOps/Admin/Pages/ClientSummary.php}).

Leandro's framing for this round: **the differences that matter are the ones improving the
readability and usability of a page or section** — not pixel identity for its own sake.

Shared chrome on every tab: the "Client Profile" heading, the client switcher select
("name (company) - #id"), then the tab strip:
Summary · Profile · Users · Contacts · Products/Services · Domains · Billable Items ·
Invoices · Quotes · Transactions · Tickets · Emails · Notes (n) · Log.

> **Domains** is in the reference's strip but deliberately absent from ours — domains were
> removed from this store (§10 of the brief), so the tab would never hold a row.

---

## Summary

Header line: `#1 - dev dev`, and on the right a flags strip —
`Exempt from Tax: No | Auto CC Processing: Yes | Send Overdue Reminders: Yes | Apply Late Fees: Yes`
(each value coloured; No red, Yes green).

Then **four columns**:

1. **Clients Information** — First Name, Last Name, Company Name, Email Address, Address 1,
   Address 2, City, State/Region, Postcode, Country, Phone Number. Footer link: *Login as
   Owner*. Below it **Contacts** ("No additional contacts setup" + *Add Contact*), then
   **Pay Methods** ("No Pay Methods" + *Add Credit Card*).
2. **Invoices/Billing** — Paid, Draft, Unpaid/Due, Cancelled, Refunded, Collections, each
   `n ($0.00 USD)`. Then an **Income** band: Gross Revenue, Client Expenses, Net Income,
   Credit Balance. Footer links: *Create Invoice*, *Create Add Funds Invoice*, *Generate Due
   Invoices*, *Add Billable Item*, *Manage Credits*, *Create New Quote*. Below it **Other
   Information** — Status, Client Group, Signup Date, Client For, Last Login.
3. **Products/Services** — counts per type: Shared Hosting, Reseller Hosting, VPS/Server,
   Product/Service, Domains, Accepted Quotes, Support Tickets, Affiliate Signups, each
   `n (n Total)`. Footer links: *View Orders*, *Add New Order*. Below it **Files** ("No files
   uploaded" + *Add File*), then **Recent Emails** (date + subject).
4. **Other Actions** — View Account Statement, Open New Support Ticket, View all Support
   Tickets, Activate as Affiliate, Merge Clients Accounts, Close Clients Account, Delete
   Clients Account, Export Client Data. Below it **Send Email** (template select + *Go*),
   then **Admin Notes** (textarea + *Submit*).

Under the columns, right-aligned: `Status Filter: Off`. Then four banded tables, each with
a navy header, "No records found", a `Show 10 entries` select, `Showing 0 to 0 of 0 entries`
and Previous/Next:

- **Products/Services** — ID, Product/Service, Amount, Billing Cycle, Signup Date, Next Due Date, Status
- **Addons** — ID, Name, Amount, Billing Cycle, Signup Date, Next Due Date, Status
- **Domains** — ID, Domain, Registrar, Registration Date, Next Due Date, Expiry Date, Status
- **Current Quotes** — ID, Subject, Date, Total, Valid Until Date, Status

Footer: `With Selected: [Invoice Selected Items] [Delete Selected Items]`, then
`Bulk Actions: [- Set Status -] [- Set Payment Method -] [ ] Do not suspend until [date]`
with *Show Advanced Options* and *Apply* on the right.

## Profile

Two columns of a real edit form, ending in **Save Changes / Cancel Changes**.
Top-right: *View Marketing Opt-In Consent History*.

- Left: First Name, Last Name, Company Name (Optional), Email Address, then Language,
  Status, Client Group.
- Right: Address 1, Address 2 (Optional), City, State/Region, Postcode, Country, Phone
  Number (with country-code picker), Payment Method ("Select to Change Default"), Billing
  Contact, Currency.
- **Email Notifications** — six checkboxes with their explanatory text: General, Invoice,
  Support, Product, Domain, Affiliate Emails, plus *Check All*.
- **Settings** — ON/OFF toggles in three columns: Late Fees, Overdue Notices, Tax Exempt,
  Separate Invoices, Disable CC Processing, Marketing Emails Opt-in, Status Update,
  Allow Single Sign-On.
- **Admin Notes** — full-width textarea.

## Users

Toolbar: **+ Associate User**. Navy table — Name / Email Address (with an `OWNER` badge),
Last Login Time, Actions (*Manage User*, red *Remove*, and a split-button caret).

## Contacts

`Contacts: [Add New ▾]` select at the top, then the same two-column form as Profile
(First/Last Name, Company, Email | Address 1/2, City, State/Region, Postcode, Country,
Phone) plus the six Email Notifications checkboxes and *Check All*.
Footer: **Add Contact / Cancel Changes**.

## Billable Items

Toolbar right: *Add Time Billing Entries*, **+ Add Billable Item**.
`Uninvoiced Items - $0.00 USD (0)` then a navy table — ID, Description, Hours, Amount,
Invoice Action. `With Selected: [Invoice Selected Items] [Delete]`.
Then `Invoiced Items`, its own records line and table — ID, Description, Hours, Amount,
Invoice Numbers — with Previous/Next.

## Invoices

Toolbar right: *Search*, **+ Create Invoice**. The search panel is the same one the main
Invoices screen carries: Invoice #, Line Item Description, Payment Method, Status, Total Due
From/To | Invoice Date, Due Date, Date Paid, Last Capture Attempt, Date Refunded, Date
Cancelled (all calendars).
Table — Invoice #, Invoice Date, Due Date, Date Paid, Total, Payment Method, Status.
`With Selected:` **Mark Paid** (green), Mark Unpaid, Mark Cancelled, Duplicate Invoice,
Send Reminder, Merge, Mass Pay, **Delete** (red).

## Transactions

Toolbar right: **+ Add New Transaction**. Four stat tiles: TOTAL IN, TOTAL FEES, TOTAL OUT,
BALANCE. Table — Date, Payment Method, Description, Amount In, Fees, Amount Out.

## Tickets

Toolbar right: search icon, **+ Open New Ticket**. Four stat tiles: OPENED THIS MONTH,
OPENED LAST MONTH, OPENED THIS YEAR, OPENED LAST YEAR. `Showing 0 to 0 of 0 entries` and a
`Search Subject:` box, then the table — Date Opened, Department, Subject, Status, Last Reply
— with a `Show 10 entries` select.

## Emails

Records line, then a navy table — Date, Subject — each row ending in two icons (resend
envelope, red delete). Previous/Next.

## Notes

Navy table — Created, Note, Admin, Last Modified — "No Records Found" when empty. Below it a
**markdown editor** (B / I / H, link, lists, code, quote, *Preview*, help, expand) with
`lines: n  words: n  saved` in its footer, and to its right **Add New** plus a
`Make Sticky (Important)` checkbox.

## Log

Toolbar right: *Filter Log*. Filter panel — Date (calendar), Username (Any) | Description,
IP Address. Records line with Jump to Page, then the table — Date, Log Entry, User (admin
name over its email, muted), IP Address.

---

## Where ours already matches

Verified live 2026-09-08: all 13 tabs render with no PHP errors, no failed requests and no
console errors. Summary already carries the four-column layout, the flags strip, Clients
Information / Contacts / Pay Methods / Invoices-Billing / Other Information and the banded
tables; Products/Services carries the reference's per-service editor field for field.

## Known gaps to work through next

1. **Summary** — confirm the four columns' panel order and the exact footer link sets;
   check the `Show n entries` selects and `Showing x to y of z` lines on the four banded
   tables, and the Bulk Actions row (Set Status / Set Payment Method / Do not suspend until
   / Show Advanced Options / Apply).
2. **Profile** — the ON/OFF toggle grid for Settings, and the Marketing Opt-In Consent
   History link.
3. **Billable Items** — the Uninvoiced/Invoiced split with its own totals line.
4. **Tickets / Transactions** — the four stat tiles above each table.
5. **Notes** — the markdown editor's toolbar and the Make Sticky option.
6. **Log** — the Filter Log panel.

Anything the store genuinely lacks (Domains, stored Pay Methods, uploaded Files) stays an
honestly-dead control with the reason on its `title`, per the standing convention.
