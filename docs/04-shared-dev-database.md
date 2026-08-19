# Shared development database (local → VPS)

Points the local project at the **server's** MariaDB so local and server share one dataset,
over an SSH tunnel. The database container is never modified and MySQL is never exposed to
the internet.

> **Read this first.** With this active, everything you do locally writes to the server's
> live data — orders, invoices, services, settings. There is no separate test copy any
> more. Do **not** run the verification suites (`scratchpad/e2e-*.php`, the PHPUnit
> scratch tests) while connected: they create users, invoices and services and change
> settings. Switch back to SQLite (bottom of this file) before running them.

---

## Why a tunnel

The MariaDB container listens only on the internal Docker network (`172.18.0.2:3306`). It
is not published to the host and not reachable from the internet — which is how it should
stay. Publishing 3306 would expose the customer database to the world.

An SSH tunnel forwards a local port to it over the connection you already trust, so no
firewall rule, no port publishing, and no change to the database container.

---

## 1. Open the tunnel

Leave this running for as long as you want the shared database. From PowerShell:

```powershell
ssh -N -L 127.0.0.1:3307:172.18.0.2:3306 root@69.197.186.115
```

To run it detached instead:

```powershell
Start-Process ssh -ArgumentList "-N","-L","127.0.0.1:3307:172.18.0.2:3306","root@69.197.186.115" -WindowStyle Hidden
```

Check it is listening:

```powershell
Test-NetConnection -ComputerName 127.0.0.1 -Port 3307 -InformationLevel Quiet   # -> True
```

If the database container is ever recreated its IP can change. Re-read it with:

```powershell
ssh root@69.197.186.115 "docker inspect paymentor-proxy-admin-panel-database-1 --format '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}'"
```

---

## 2. Get the database password

```powershell
ssh root@69.197.186.115 "docker inspect paymentor-proxy-admin-panel-database-1 --format '{{range .Config.Env}}{{println .}}{{end}}' | grep '^MYSQL_PASSWORD='"
```

---

## 3. Point the local `.env` at the tunnel

Replace the `DB_*` block in `.env` (it is gitignored, so the password stays out of git):

```dotenv
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=paymenter
DB_USERNAME=paymenter
DB_PASSWORD=<the password from step 2>
```

Then clear the cached config and confirm:

```
php artisan config:clear
php artisan tinker --execute="echo DB::connection()->getDatabaseName(), ' / users=', App\Models\User::count(), PHP_EOL;"
```

You are connected when the user count matches the server's rather than your local SQLite one.

---

## Switching back to local SQLite

The original file was saved as `.env.sqlite.bak` before the switch:

```powershell
Copy-Item .env.sqlite.bak .env -Force
php artisan config:clear
```

Then close the tunnel (`Ctrl+C`, or `Stop-Process -Name ssh`).

---

## Notes

- `artisan serve` on Windows strips `TMP`/`TEMP` from the child process, which breaks
  SQLite writes — see `docs/VERIFICATION.md`. That fault is specific to SQLite, so it
  disappears while you are on MariaDB. Use `--no-reload` when you switch back.
- Migrations run locally now apply to the **server** database. Treat `php artisan migrate`
  as a production action while connected.
- Back up before anything schema-changing:
  ```
  ssh root@69.197.186.115 "P=\$(docker inspect paymentor-proxy-admin-panel-database-1 --format '{{range .Config.Env}}{{println .}}{{end}}' | grep '^MYSQL_PASSWORD=' | cut -d= -f2-); docker exec -e MYSQL_PWD=\"\$P\" paymentor-proxy-admin-panel-database-1 mariadb-dump -u paymenter --single-transaction --routines paymenter | gzip > /root/backups/manual-\$(date +%Y%m%d-%H%M%S).sql.gz"
  ```

---

## The URL trap (added after this bit me)

Sharing the database also shares the `app_url` **setting**, which `SettingsProvider` copies
into `config('app.url')` and pins with `URL::forceRootUrl()`. Connected, that value is
`https://paymenter-dev.7hoop.net`, so every generated link, form action, asset and redirect
leaves your machine — the local site loads and nothing works; a login posts to the server.

Changing the setting is not an option: it is the row the live site reads.

Enable the **Local Dev Overrides** extension and set `LOCAL_APP_URL` in `.env`. It re-applies
a local URL after settings load (extensions boot from `AppServiceProvider::boot()`, after
`SettingsProvider`). It requires *both* `APP_ENV=local` and `LOCAL_APP_URL`, so it can never
affect the server.

```dotenv
LOCAL_APP_URL=http://127.0.0.1:8081
```

Confirm no generated URL points at the server:

```sh
curl -s http://127.0.0.1:8081/login | grep -oE 'https?://[a-z0-9.:-]+' | sort -u
```
