# Client Tools

Adds the client-area pages the reference portal (`my.noxproxy.com`) has and Paymenter does
not ship, plus the **Apply Credit** panel on an invoice.

Enable under **Admin → Extensions → Client Tools**. Enabling runs the extension's
migrations, which create `ext_ct_contacts` and `ext_ct_downloads`.

## What each page is backed by

| Page | Route | Backed by |
|---|---|---|
| My Quotes | `/quotes` | *nothing* — see below |
| Mass Payment | `/billing/mass-payment` | `invoices`, settled from `credits` |
| Downloads | `/downloads` | `ext_ct_downloads` (Admin → Client Tools → Downloads) |
| Contacts | `/account/contacts` | `ext_ct_contacts`, managed by the customer |
| User Management | `/account/users` | sub-account contacts + core `user_sessions` |
| Email History | `/account/email-history` | core `email_logs` |
| Available Addons | `/addons` | core `product_upgrades` via `Service::productUpgrades()` |

Five of the seven read data that already exists, so they show real information rather than
placeholder chrome.

## Quotes is deliberately empty

Paymenter has no quoting system. An invoice is only ever `pending`, `paid` or `cancelled` —
there is no draft/proposal state that could stand in for a quote without presenting a real
invoice as something it is not. The page therefore renders the same empty state the
reference portal shows for this account, and the dashboard's QUOTES counter reads 0, which
is also what the reference shows.

To wire in a future quoting extension, fill the collection in
`Livewire/Quotes.php::render()`; the menu entry, page and counter are already in place.

## Mass Payment applies credit, it does not batch a gateway payment

The reference batches several invoices into one gateway charge. Paymenter's gateway
contract (`ExtensionHelper::pay()`) takes a single invoice, so batching would mean creating
a synthetic invoice and reconciling it afterwards — a failure mode that goes wrong quietly
with real money.

What is offered instead is the part that can be done atomically: apply account credit
across the selected invoices, oldest first. An invoice is settled only when the balance
covers it **in full**; a partial application would leave the customer with neither the
credit nor a paid invoice. Each invoice is settled in its own locked transaction, so an
interruption leaves earlier invoices genuinely paid and re-running is safe.

## Apply Credit on an invoice

Core can already pay an invoice from credit, but only all-or-nothing — `payWithCredit()`
spends the whole balance up to the invoice total. The reference lets the customer choose
an amount and keep the rest, which is what `Livewire/ApplyCredit.php` adds.

Both sides are re-read under `lockForUpdate()` on submit and the amount is clamped to what
is actually available, so a stale form cannot spend credit that has since been used
elsewhere or overpay an invoice.

The panel is rendered by the theme at
`themes/proxy/views/invoices/show.blade.php`, guarded on this extension's class existing —
disabling Client Tools removes the panel rather than breaking the invoice page.

## Security notes

- Every query is scoped to `Auth::id()`, and Contacts/Email History re-fetch by
  `(id, user_id)` on write and on expand, so an id swapped into the request cannot reach
  another account's row.
- Email History renders the stored message inside `<iframe sandbox="">`. Bodies are HTML
  and can quote customer-supplied text (a ticket reply in a notification), so they are
  never interpolated into the page; the sandbox blocks scripts, forms and same-origin
  access while preserving the mail's formatting.
- Downloads re-checks visibility on fetch, so a customers-only file cannot be pulled by
  posting its id while signed out.
