# Provisioning Operations (failure log + retry)

Makes failed provisioning **visible to the admin and retryable**, and stops a failed
provision from leaving an order silently "active".

- **Location:** `extensions/Others/ProvisioningOps/`
- **Type:** Other (admin mini-app)
- **Admin screen:** **Services → Provisioning** (badge shows outstanding failures)

## Why it exists

Paymenter activates a service and dispatches provisioning as a **queued job**
(`App\Services\Service\RenewServiceService`):

```php
if ($service->status == Service::STATUS_PENDING) {
    CreateJob::dispatch($service);      // runs later, on the queue worker
}
$service->status = Service::STATUS_ACTIVE;   // …but the status is saved immediately
$service->save();
```

With a real queue driver (`redis`/`database`, i.e. production) the status is committed
**before** the panel is ever contacted. If the panel API is down, `CreateJob` fails on the
worker and nothing points back at the service: the customer has been billed for, and can
see, an "active" proxy service that was never provisioned. `CreateJob` has `$tries = 1`,
so there is no second attempt either.

The brief calls this out explicitly: *"a provisioning failure must never silently mark an
order active"*.

## What it does

A server extension calls `ProvisioningOps::failed()` from its error path. That:

1. **records the failure** — one row per `(service, extension, action)`, with the error
   message, an attempt counter and a JSON context blob;
2. **protects the service status** — a failed `create` on a service that is already
   `active` is reverted to `pending`, so it is never silently active;
3. **surfaces it in the admin** with a one-click **Retry**.

`ProvisioningOps::succeeded()` closes the row again, so the list only ever shows things
that are actually broken.

Both entry points are null-safe and guarded by a table-existence check, so a server
extension can call them unconditionally — including when this module is disabled.

## Wiring it into a server extension

`Servers/ProxyPanel` does this inside its per-action lock, which is the only place every
lifecycle call passes through:

```php
try {
    $result = $callback();
    ProvisioningOps::succeeded($service, 'ProxyPanel', $action);

    return $result;
} catch (\Throwable $e) {
    ProvisioningOps::failed($service, 'ProxyPanel', $action, $e, [
        'api_url' => (string) $this->config('api_url'),
    ]);

    throw $e;               // the caller still sees the failure
}
```

`$action` is one of `create`, `suspend`, `unsuspend`, `terminate`, `upgrade`, `callback`.

## Retry

The **Retry** button re-runs the real lifecycle call through
`ExtensionHelper::callService($service, $method)` — the same path core uses — mapping:

| action | method |
|---|---|
| `create` | `createServer` |
| `suspend` | `suspendServer` |
| `unsuspend` | `unsuspendServer` |
| `terminate` | `terminateServer` |
| `upgrade` | `upgradeServer` |

`callback` rows are informational (an unrecognised panel state) and have no Retry button.

Because the extension records its own success/failure, the list stays accurate whatever
the retry does: a successful retry closes the row, a failed one increments `attempts`.

## Schema

`provisioning_operations` — `service_id`, `extension`, `action`, `status`
(`failed`/`succeeded`), `attempts`, `error`, `context` (json), `resolved_at`,
`last_attempt_at`, timestamps. Unique on `(service_id, extension, action)` so repeated
failures collapse into a single row rather than flooding the list.

## Verified

See `docs/VERIFICATION.md` — the "panel broken → failure recorded → order not left active
→ retry resolves it" sequence is exercised end-to-end against `scripts/mock-proxy-panel.php`.
