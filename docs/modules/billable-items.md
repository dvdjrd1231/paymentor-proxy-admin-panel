# Billable Items

Charging a customer for something that is not a product.

**Billing → Billable Items.** The reference's feature, its Invoice Actions, its recurring
cycle and its bulk "Invoice Selected Items".

## Why it is needed

Everything Paymenter can bill for has to be a product somebody ordered. There is no way to
charge for a one-off — an hour of setup, a manual IP change, a block of addresses outside a
plan, a chargeback fee — without inventing a product for it and pretending the customer
ordered one. That pollutes the catalogue and makes the order history a lie.

A billable item is a line written against a customer that lands on their next invoice.

## Invoice Action

The reference's list, less the two that only mean something inside its own due-date model:

| Action | What happens |
|---|---|
| **Add to the customer's next invoice** *(default)* | Waits. Hooked to `Invoice::created`, so the renewal invoice the cron was going to raise anyway simply arrives with an extra line. |
| **Invoice on the next daily run** | Gets an invoice of its own on the daily sweep. |
| **Don't invoice for now** | Recorded, visible, never swept. Different from deleting it because somebody decided, and can undecide. |

Waiting is the default because it is almost always right: a small charge on an invoice of its
own costs more in payment fees and attention than it collects.

**An item set to wait does not wait for ever.** A customer with no recurring service would
never get another invoice, so anything that has waited more than twice the invoice lead time
(`cronjob_invoice`, default 7 days → 14) is given one of its own.

## Decisions worth knowing

**Hours and amount are kept apart**, as the reference keeps them. "3 hours at 40" is what
somebody wants to see again in six months; a single total of 120 has thrown away which of
the two was wrong.

**A recurring item is queued again as a new row**, not by resetting the old one. What was
charged in March stays attached to March's invoice. An item whose history is overwritten each
cycle can answer "what is due" but not "what did we bill them" — which is the question that
comes up in a dispute.

**One invoice per customer *and* per currency.** Paymenter stores no exchange rate, so a line
in BRL and a line in USD on one invoice would produce a total that is neither.

**An invoiced item cannot be edited.** It is a line on an invoice the customer is holding;
changing it here would change the record and not the invoice, and the invoice is the one that
matters.

**Riding along skips drafts.** An invoice nobody has published yet is still being written, and
adding lines to it behind the author's back is exactly the surprise the draft status exists
to prevent.

**Attaching can never break the invoice it rode along on.** If it fails, the invoice is still
valid and still the customer's; the item stays uninvoiced and the sweeper picks it up.

The daily sweep reports itself on **Automation Status** as *Billable Items — Invoiced*.

## Uninstalling

Items already invoiced are lines on invoices, which are core's and stay. What goes is the
queue of uninvoiced ones — money written down and not yet charged for. Worth knowing before
disabling this.
