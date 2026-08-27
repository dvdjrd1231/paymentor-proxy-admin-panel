# Cancellation Requests

Acts on cancellation requests, and lets an administrator accept or refuse one.

## The bug this fixes

`service_cancellations.type` is `immediate` or `end_of_period` — and **nothing in core reads
it**. The only place a cancellation is consulted is the invoicing branch of `app:cron-job`:

```php
if ($service->invoices()->where('status','pending')->exists() || $service->cancellation()->exists()) {
    return;   // no renewal invoice
}
```

So every request, whichever type the customer chose, does one thing: it stops the next
invoice. A customer who asks to cancel **immediately** keeps a working proxy until the expiry
ladder catches up — 2 days to suspend, 14 more to terminate — and on a one-time plan, whose
`expires_at` is NULL, for ever. The proxies stay allocated on the panel the whole time, so
the capacity is gone too.

There was also no way to accept or refuse a request: core's list offers Edit and Delete, and
deleting is indistinguishable from refusing.

## Following the reference

WHMCS's model, read out of `admin/lang/english.php` (the only unencoded part of a nulled
copy, and authoritative for behaviour):

| WHMCS | Where |
|---|---|
| *"Check to automatically terminate accounts with cancellation requests **when due**"* | Automation Settings → Cancellation Requests |
| A **Cancellation Requests** task on Automation Status, with success/failure counts | `utilities.automationStatusDetail.task.cancellationRequests` |
| *"A cancellation request exists for this item and so it will not be invoiced when due"* | the service page — Paymenter already behaves this way |
| *"automatically cancel outstanding unpaid invoices when a cancellation request is submitted"* | General Settings — **not implemented here**, see below |

So the reference terminates on the due date, automatically, governed by one switch, and
reports itself as an automation task. That is what this now does.

**Two deliberate differences.** WHMCS runs the task once a day because its entire automation
is a single daily cron; here the scheduler already ticks every minute, so immediate requests
are acted on as they are made and the sweep runs hourly — an end-of-period service left live
until tomorrow morning is another day of proxy capacity spent on a service both sides agreed
was finished. And WHMCS's *cancel unpaid invoices on submission* is not implemented: this
cancels them on **termination** instead, so a customer who is refused, or who cancels at
period end and changes their mind, is not left chasing an invoice that was voided
prematurely.

## What it does

- An **immediate** request terminates the service as soon as it is made — releasing the
  proxies, cancelling any unpaid invoice for that service, and returning stock.
- **End-of-period** requests terminate **when the period ends** — the reference's "when due".
  Before this they only stopped the next invoice, and the service then fell into the ordinary
  overdue ladder: live for two days past the period it was paid for, then suspended for twelve
  more with its proxies still allocated. Fourteen days of capacity for a service both sides
  agreed was finished.
- **Clients → Cancellation Requests** gains **Accept now** and **Refuse**. Refusing deletes
  the request, which is the whole of un-cancelling — core decides "is this cancelled" by the
  row existing.

Automatic acceptance is a setting (`auto` / `review`), because "immediate" is the customer's
word. In `review` mode the menu badge counts what is waiting.

## Why a second resource and not actions on core's list

A resource's table cannot be extended from an extension: `Table::configureUsing()` runs
inside `Table::make()`, and the resource's own `table()` then calls `->recordActions([...])`,
which resets the array before repopulating it. The same trap is documented for AdminOps'
Summary link. Core's list is untouched; the WHMCS menu points at this one, and falls back to
core's if this extension is not installed.
