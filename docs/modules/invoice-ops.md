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

## 4. Transactions — Amount In, Fees, Amount Out

**Billing → Transactions Report.** The reference's three tiles (Total Income, Total Fees,
Total Expenditure) and its columns.

Core's transaction list has **Amount** and nothing else, which makes an ordinary question
unanswerable: *what did we actually keep*. A gateway takes its cut before the money arrives
and a refund gives some of it back, so gross receipts are not revenue.

| Column | Source |
|---|---|
| Amount In | `invoice_transactions.amount` — core records this |
| **Fees** | `invoice_transactions.fee` — **this column has existed since the table was created and nothing has ever written to it** |
| **Amount Out** | refunds, from this extension |

Payments and refunds interleave in one list rather than sitting in two tables: *"paid, then
half of it refunded a week later"* is only obvious when the two lines are next to each other.

The Fees tile reads zero **and says why**. None of the four gateways here reports its cut
back to Paymenter, so a bare zero would imply no fees were charged rather than "nothing has
ever written this column". Making it real means each gateway reading its fee from the webhook
payload — Stripe's `balance_transaction`, and so on — which is gateway work, not reporting
work. It is shown rather than hidden so the difference is visible instead of assumed.

Totals are per currency, never summed across them: Paymenter stores no exchange rate.

## 5. Refund requests — the answer to what blocked gateway refunds

**Billing → Refund Requests.** The customer asks, an administrator answers.

This is the shape that makes refunds workable without a gateway API. Paymenter cannot push
money back through Stripe — no gateway here defines `refund()` — but the **decision** needs
no API. You approve here, refund in the gateway's own dashboard, and the approval writes the
record the ledger and the Amount Out column read.

The money and the record are two acts by one person, in that order, and the screen says so
rather than implying the approval moved anything.

**Approving is two statements, not one:** how much goes back, and whether the service goes
with it. A customer asking for money back is not necessarily asking to lose their proxy, and
the two have very different consequences — so cancelling is off by default and never implied.

The administrator is **not bound by the amount asked for**. A part refund is a legitimate
answer to "give me all of it", and forcing a yes/no on the customer's number would push those
conversations off the record entirely.

**Refusing requires a reason.** A refusal with nothing behind it is the one a customer
escalates, and the one nobody can defend three months later when it returns as a chargeback.

One open request per invoice at a time: a second while one is still open is the same
conversation twice, and answering both separately is how a customer gets refunded twice. The
approved request keeps its refund's id, which is what stops one request becoming two refunds
if the button is pressed twice.

## 6. Refunds ledger

**Billing → Refunds.** Every refund, read-only — a refund is a fact about money that has
already moved, and correcting one is another refund with its own reason, not an edit that
rewrites what the books say happened.

The reference keeps refunds on each invoice and has no page quite like this. Its Transactions
page has an *Amount Out* column that has to come from somewhere, and "show me everything we
gave back this month" is a question an invoice-at-a-time view cannot answer.

## Uninstalling

The refund records go with it. An invoice left on `draft` or `refunded` keeps that status:
both are strings core tolerates, and rewriting billing history on an uninstall would be the
more surprising behaviour. **A draft does become visible to the customer again**, which is
worth knowing before disabling this.
