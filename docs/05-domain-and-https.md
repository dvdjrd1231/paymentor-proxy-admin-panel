# Domain and HTTPS

The VPS is served at **<https://paymenter-dev.7hoop.net>** (was `http://69.197.186.115`).

DNS resolves to Cloudflare, which terminates TLS and forwards to the origin over plain
HTTP on port 80. Nothing needed installing on the server — no certbot, no nginx changes.

---

## What had to change in the application

Three separate places set the URL, and only changing `.env` is **not** enough.

### 1. `app_url` setting — the authoritative one

Paymenter stores the site URL as a **database setting** which overrides `config('app.url')`
(see `App\Classes\Settings`, `'override' => 'app.url'`). Editing `.env` alone leaves the
old value in force, because the setting wins.

**Admin → Settings → App URL**, or:

```php
App\Models\Setting::updateOrCreate(
    ['key' => 'app_url', 'settingable_type' => null],
    ['value' => 'https://paymenter-dev.7hoop.net']
);
Cache::forget('settings');
```

This is what `route()` builds webhook URLs from, so it must be right before any gateway is
registered.

### 2. `trusted_proxies` setting — required behind Cloudflare

Cloudflare forwards over HTTP, so without trusting the proxy the app sees an insecure
request and generates `http://` URLs — which then answer a 301 to HTTPS. A 301 on a webhook
**POST** loses the body, so callbacks would silently stop working.

Paymenter has native support via `ProxyMiddleware`, driven by the `trusted_proxies`
setting. Set under **Admin → Settings**, or:

```php
App\Models\Setting::updateOrCreate(
    ['key' => 'trusted_proxies', 'settingable_type' => null],
    ['value' => json_encode(['*']), 'type' => 'array']
);
```

The `type` column matters: values are decoded on retrieval based on it
(`App\Events\Setting\Retrieved`). Saved without `type => 'array'` the value comes back as a
string and `ProxyMiddleware`'s `count()` fails.

> `*` trusts whichever proxy connects. The origin is still reachable directly on port 80,
> so for production either restrict this to Cloudflare's published ranges or block port 80
> at the firewall except from Cloudflare.

### 3. `APP_URL` / `PUBLIC_URL` for the container

`docker-compose.vps.yml` sets `APP_URL: ${PUBLIC_URL:?...}`, so `PUBLIC_URL` must exist in
`/opt/paymentor-proxy-admin-panel/.env` — it was missing, which is why `docker compose`
commands failed with an interpolation error. The app's own `.env` lives in the `appvar`
volume at `/var/lib/docker/volumes/paymentor-proxy-admin-panel_appvar/_data/.env`.

Recreate only the app container, leaving the database and cache untouched:

```bash
cd /opt/paymentor-proxy-admin-panel
DB_PASSWORD=$(docker inspect paymentor-proxy-admin-panel-database-1 \
  --format '{{range .Config.Env}}{{println .}}{{end}}' | grep '^MYSQL_PASSWORD=' | cut -d= -f2-) \
  docker compose -f docker-compose.vps.yml up -d --no-deps paymenter
```

---

## Webhooks must be re-pointed after any URL change

| Gateway | What to do |
|---|---|
| **Stripe** | Endpoint URL is registered at Stripe. The old `69.197.186.115` endpoints were deleted and a new one created for the new domain, and its signing secret stored. |
| **CoinPayments** | The notification URL is registered **per invoice** from `route()`, so new invoices pick it up automatically. The signature is computed over that URL, so invoices created under the old URL will fail verification — expected, and harmless for test invoices. Update the integration's webhook URL in the CoinPayments dashboard too. |

Re-run both end-to-end checks after any URL change:

```
php scripts/test-stripe-payment.php 15
php scripts/test-coinpayments-payment.php 5
```

Both passed on the new domain (Stripe 7/7, CoinPayments 6/6).

---

## Note on `scripts/`

`scripts/` is **not** a mounted volume, so the test scripts vanish whenever the container is
recreated. Copy them back in when needed:

```bash
C=paymentor-proxy-admin-panel-paymenter-1
docker exec $C mkdir -p /app/scripts
docker cp /opt/paymentor-proxy-admin-panel/scripts/test-stripe-payment.php $C:/app/scripts/
```

---

## Trap: running `artisan` as root breaks logging (and webhooks)

`docker exec` runs as **root**, so any `php artisan` command run that way creates that day's
log file owned by root. The web process runs as a different user and can then no longer
append to it — and because a logging failure happens *inside* exception handling, the
request returns **500** rather than its intended response.

The symptom is confusing: pages keep working, but every endpoint that logs starts failing.
All three of our gateway webhooks returned 500 while Stripe's kept working, purely because
Stripe's handler returns 400 without writing a log line first.

After running artisan inside the container, restore ownership:

```bash
C=paymentor-proxy-admin-panel-paymenter-1
docker exec $C sh -c 'chown -R nginx:nginx /app/storage/logs; chmod -R 0775 /app/storage/logs'
```

Check with `ls -la storage/logs` — today's file should not be `root root`.
