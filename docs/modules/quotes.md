# Quotes

A priced proposal a customer can accept, which then becomes an invoice.

**Billing → Quotes** in the admin; **My Quotes** in the client area.

## Why a new record and not a draft invoice

Paymenter has no document that is not already a bill. An invoice is `pending`, `paid` or
`cancelled`, and none of those can stand in for *"here is what it would cost"* without
misrepresenting a proposal as a debt: the customer sees it among their invoices, the overdue
ladder starts counting, and a reminder goes out for money nobody agreed to pay.

So a quote is its own record, with its own life, and becomes an invoice only when somebody
accepts it.

## The life of one

```
draft ──send──▶ sent ──▶ accepted  (raises a real invoice)
                    ├──▶ declined
                    └──▶ expired   (the daily sweep, only if it has a date)
```

Every transition is one-way and guarded on the state before it. An accepted quote cannot be
declined, an expired one cannot be accepted by a stale browser tab, and none can happen
twice. **Those guards are the whole safety of the feature** — an accepted quote creates a
real invoice, and creating two creates a debt that does not exist.

## Decisions worth knowing

**Draft is invisible to the customer**, exactly as with draft invoices, and a quote can only
be **edited while it is a draft**. Once sent it is a document they are looking at, and
changing the price under them is the one thing a quoting system must never do. **Duplicate**
is what you want instead — last quarter's proposal with two numbers changed is the thing
anyone asks for after the first month.

**A lapsed quote is still acceptable.** Past its date it shows the customer a warning, not a
refusal, and stays answerable until the daily sweep closes it. Someone accepting at one
minute past midnight on the closing day has done what was asked of them; losing that sale to
a cron schedule would be a self-inflicted wound. What closes a quote is the sweep, not the
clock.

**A quote with no date is never expired.** An open-ended offer is a legitimate thing to make,
and expiring it because the column is empty would invent a deadline nobody agreed to.

**Accepting is one transaction.** The invoice is created and the quote marked accepted
together, and the quote keeps the invoice's id — so two tabs or two presses produce one
invoice.

**An administrator can accept on the customer's behalf**, as the reference allows, for when
somebody says yes on the telephone. It raises the same invoice.

**A declined quote is kept, not deleted.** It is a sales record.

**Sending emails on a best effort.** A quote visible in the portal whose email failed is one
the customer can still find and accept; one never sent because the mail server was down is a
sale lost to an outage. It needs a `quote_sent` notification template to mail anything.

## The client area

`Others/ClientTools` shipped **My Quotes** as an empty state with a note saying *"a future
quoting extension only has to fill this collection"*. This is that extension. The dependency
runs one way and softly: without Quotes installed the table is absent, the collection is
empty, and the page renders the empty state it always did.

Accepting there checks ownership on the id from the browser — without it, anyone signed in
could accept somebody else's quote and create a debt on their account.

The expiry sweep reports on **Automation Status** as *Quotes — Expired*.
