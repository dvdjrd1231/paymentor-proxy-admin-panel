# Fixed-Term Products

Daily and weekly products end when their contracted time is up. Monthly products are
untouched and go on renewing.

## The bug this fixes

Paymenter's daily/weekly plans are `one-time`, and `Service::calculateNextDueDate()` returns
**null** for a one-time plan — so an activated daily service is stored with
`expires_at = NULL`. Every branch of core's cron that would end it compares against that
column, and `NULL < anything` is never true in SQL:

```php
Service::where('status', 'active')->where('expires_at', '<', now()->subDays(2))      // suspend
Service::where('status', 'suspended')->where('expires_at', '<', now()->subDays(14))  // terminate
```

Neither ever matches. **A daily proxy runs for ever** — the customer pays for one day and
keeps it. No renewal invoice is raised either, which is correct, and is why nobody noticed:
the service simply never ends.

## What it does

- A clock per service in **hours**, starting when the service goes live (not when it was
  ordered — an order that waited overnight for provisioning has used none of its hours).
- A **sweeper every minute** stops what is due. Core's cron runs once a day, which is right
  for a monthly product and wrong for a daily one.
- **Non-renewable by construction**: nothing here writes `expires_at`, so core's invoicing
  branch still cannot see these services and still raises no renewal invoice.
- **Extensions of time** are an admin action with a required reason, appended to a record
  that is never edited. Orders → **Fixed Terms**.

Expiry **terminates** by default (releasing proxies back to the panel) or **suspends**, per
the extension's setting.

## Commands

```sh
php artisan term-limits:enforce --dry-run    # what would stop, changes nothing
php artisan term-limits:enforce              # stop what is due
php artisan term-limits:enforce --backfill   # open terms for services already running
```

`installed()` runs the backfill automatically, giving each already-running service a full
term from *now* — a customer who has had an unmetered proxy for weeks through no fault of
their own should not lose it the moment this is switched on.

Full documentation: [`docs/modules/term-limits.md`](../../../docs/modules/term-limits.md).
