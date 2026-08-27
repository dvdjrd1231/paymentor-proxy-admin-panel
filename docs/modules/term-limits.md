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



## Auto Terminate/Fixed Term — the reference's field

WHMCS puts this on the product's **Pricing** tab:

> **Auto Terminate/Fixed Term** — *"Enter the number of days after activation to
> automatically terminate (eg. free trials, time limited products, etc...)"*
> **Termination Email** — *"Choose the email template to send when the fixed term comes to
> an end"*

Both now exist, under **Setup → Auto Terminate**, one row per product.

It is a screen rather than a field on the product form for the same reason the Cancellation
Requests actions are a second resource: a resource's `form()` replaces whatever an extension
pushes into it, exactly as its `table()` does.

**Most products need nothing set.** A one-time daily plan already says one day, and all
twenty of this store's daily and weekly products derive their term with no configuration.
The reference needs the field because a WHMCS "One Time" product carries no period at all;
Paymenter's does. The **From** column says which is which for the whole catalogue — set here,
or derived — which the reference has no equivalent of.

What the field reaches that derivation cannot is a **fixed term on a recurring product**:
"monthly plan, terminates after 3 days" is a free trial, and no billing cycle can express it.

**The Termination Email is new behaviour, not just a setting.** Until now a fixed-term proxy
simply stopped working and the customer was told nothing — a support ticket every time. The
term now sends the template that product names, falling back to core's `server_terminated`.
There is deliberately no "send nothing" option. Sending can never fail the termination: the
service is already stopped and the panel has already released it by the time the mail is
attempted.

Changing a product's term affects **new services only**. A term already open was bought at
the old length, and re-timing it afterwards would change what somebody paid for.

## Design decisions worth knowing

**Why a table beside `services` and not `services.expires_at`.** Core casts `expires_at` to
`date`, so it cannot hold an hour — and a daily product measured to the day runs somewhere
between one and two days. The clock also belongs to this extension: disable it and the table
is simply not read.

**Why hours are stored, not derived.** `ext_term_limits.hours` is written when the term
opens. A plan re-timed or re-priced next month must not silently change the length of a term
somebody has already paid for.

**Why months are excluded.** "One month in hours" is not a number — 28 days in February, 31
in March — so a monthly term would be wrong twice a year. Monthly products are the renewable
kind in the brief, and core already renews them correctly.

**Why an extension is added to `ends_at`, not to now.** An outage that cost a customer six
hours costs them six hours wherever in the term it happened. Extending from *now* would
quietly lengthen or shorten the term depending on how fast the ticket was answered.

**Why the term is closed before the panel is called.** If the panel call fails, the job's own
retry handles it. The other way round, a failure between the two leaves a term the sweeper
picks up again on its next pass — terminating the same service every minute.

**Why the sweeper closes terms whose service already stopped.** A service cancelled by hand
leaves an overdue open term. Left alone it is reconsidered every minute for the life of the
install; it is closed as `released`, without calling the panel, because those proxies are
already back.

## Open question — what "usage hours" means

The brief says the term is *"based on usage hours equivalent to the contracted period"*.
This implements **elapsed hours from activation**: a 1-day product runs 24 hours from the
moment the panel confirms it.

The other reading is **metered** usage — only counting hours the proxy actually carried
traffic. That is a different feature and needs the panel to report per-service usage, which
`docs/client-brief/api.md` does not currently expose. If that is what was meant, it is a
panel-side question before it is a Paymenter one.
