# Invoice Operations

Draft invoices, recorded refunds, and sending an invoice notice by hand — the three things
the reference's invoice page can do that Paymenter's cannot.

**Billing → Invoice Operations**, plus a **Draft Invoices** filter beside the other statuses.

## 1. Draft — the one that changes behaviour

The reference says it on every draft:

> *"This is a Draft Invoice. The client is not able to see or access this invoice until it is
> published."*

Paymenter has `pending / paid / cancelled` and **no draft**. `App\Livewire\Invoices\Index`
lists `Auth::user()->invoices()` with no status filter at all, so an invoice is visible to
the customer the instant it exists. Drafting one to check the figures shows them a bill you
were still writing.

`invoices.status` is a plain string column, so `draft` needs no migration. What it needs is
the half that makes it mean something: a **global scope** on the Invoice model, excluding
drafts for anyone who is not an administrator.

A scope rather than patching the components that list invoices, because those are core *and*
because they are not the only readers — the dashboard, the navigation badge and the theme all
count invoices too, and each would have to be found and fixed. One scope covers every query
that will ever be written.

It deliberately **does not apply in the console**. The daily cron reads invoices to chase and
settle them, and a scope silently removing rows from a job nobody watches is exactly the
fault that surfaces months later as "why was this never invoiced".

**Publish** moves the invoice to `pending`, not to a new state — that is what an unpaid
Paymenter invoice already is, and everything downstream is written against it. Draft is a
state *before* the normal life of an invoice, not beside it. The reference's two buttons
(Publish / Publish and Send Email) are one action with a checkbox, because two buttons that
differ by one decision is how somebody sends an email they meant not to.

## 2. Refunds — recorded, not executed

The reference's Refund tab, field for field: which transaction, how much (*"leave blank for
full refund"*), the refund type, *"Reverse Payment — undo automated actions triggered by this
transaction"*, and whether to email.

**It does not call the gateway.** Paymenter has no refund contract — neither
`ExtensionHelper` nor any of the four gateways defines one — so "refund through gateway"
would mean writing and testing a `refund()` for Stripe, CoinPayments, Cryptomus and Binance
against real money. That is its own piece of work, and faking it would be worse than leaving
it out: an administrator who believes the money went back when it did not is a chargeback and
a lost customer.

So you refund in the Stripe dashboard or by transfer, and record it here. The record is what
makes the money visible — what was given back, by whom, why, and whether the service went
with it.

Two decisions worth knowing:

- **A part refund leaves the invoice `paid`.** It *was* paid; half-changing the status would
  make the books disagree with the bank. Only a full refund moves it to `refunded`.
- **Refunds are their own table, not negative transactions.** A negative
  `invoice_transactions` row would have made income net itself out for free — and would also
  have made `Invoice::$remaining` positive again, because core computes what is owed by
  summing transactions. The refunded invoice would then read as unpaid, and the daily cron
  would chase it and eventually suspend the service. A refund is not a debt.

**Reverse Payment** cancels the services the invoice paid for and tells the panel to release
them. Off by default and never implied — refunding an overpayment should not take the
customer's proxy away.

## 3. Sending a notice by hand

Core ships fifteen invoice templates and no way to fire one at a single invoice; everything
belongs to the cron, so nudging one customer means waiting for all of them to be nudged. The
reference puts that dropdown beside the invoice status, and so does this. Only **enabled**
templates are listed — a disabled one would silently send nothing.

## Uninstalling

The refund records go with it. An invoice left on `draft` or `refunded` keeps that status:
both are strings core tolerates, and rewriting billing history on an uninstall would be the
more surprising behaviour. **A draft does become visible to the customer again**, which is
worth knowing before disabling this.
