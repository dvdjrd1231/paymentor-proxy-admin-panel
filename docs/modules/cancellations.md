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

## What it does

- An **immediate** request terminates the service as soon as it is made — releasing the
  proxies, cancelling any unpaid invoice for that service, and returning stock.
- **End-of-period** requests are untouched. Core handles them correctly by not invoicing
  again, and ending one early would take away time the customer has paid for.
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
