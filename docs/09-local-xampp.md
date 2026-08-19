# Running locally with XAMPP

XAMPP's MariaDB is used as-is; its PHP is not. XAMPP 8.2.x ships PHP 8.2, and this project
needs **8.3 or newer** — that is a hard floor, not a preference: `openspout/openspout` is a
runtime dependency requiring `~8.3.0`, and `composer.json` pins `config.platform.php` to
`8.3.0`, so Composer aborts before Laravel loads:

```
Composer detected issues in your platform: Your Composer dependencies require a PHP
version ">= 8.3.0". You are running 8.2.12.
```

A standalone PHP 8.3 sits beside XAMPP. Nothing in the XAMPP install is modified.

## One-time setup

**1. PHP 8.3 (non-thread-safe build, no installer)**

Download `php-8.3.33-nts-Win32-vs16-x64.zip` from <https://windows.php.net/downloads/releases/>
and unzip it anywhere, e.g. `C:\php83`. Next to `php.exe` create `php.ini`:

```ini
extension_dir = "ext"

extension=curl
extension=fileinfo
extension=gd
extension=intl
extension=mbstring
extension=openssl
extension=pdo_mysql
extension=pdo_sqlite
extension=sqlite3
extension=sodium
extension=zip
extension=exif

memory_limit = 512M
date.timezone = UTC
```

`bcmath`, `tokenizer` and `xml` are compiled in — listing them as `extension=` only produces
startup warnings. Verify with `C:\php83\php.exe -m`.

**2. Database** — start MySQL from the XAMPP control panel, then:

```sh
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE paymenter_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

**3. `.env`**

```env
APP_ENV=local
APP_URL=http://127.0.0.1:8080

DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=paymenter_local
DB_USERNAME=root
DB_PASSWORD=

# XAMPP ships MariaDB 10.4. Laravel 12's mariadb driver defaults to the collation
# utf8mb4_uca1400_ai_ci, which only exists in MariaDB 11.4+, and the connection fails with
#   SQLSTATE[HY000]: General error: 1273 Unknown collation
DB_COLLATION=utf8mb4_unicode_ci
```

**4. Build it**

```sh
C:\php83\php.exe artisan migrate --force
C:\php83\php.exe artisan db:seed --force
C:\php83\php.exe artisan app:settings:change app_url "http://127.0.0.1:8080"
C:\php83\php.exe artisan app:settings:change theme "proxy"
C:\php83\php.exe artisan app:user:create Local Admin admin@local.test 'LocalDev!2026' 1
```

`app_url` is the setting that matters. Paymenter takes its canonical URL from the **database
setting**, not `APP_URL`; while the two disagree every request redirects to the setting's
host, so the site loads and nobody can log in.

Enable the extensions you need in **Admin → Extensions**, or from tinker.

## Running it

```sh
C:\php83\php.exe artisan serve --host=127.0.0.1 --port=8080 --no-reload
```

`--no-reload` is not optional on Windows: the file watcher spawns the worker without `TMP`
and `TEMP`, and anything writing a temp file (SQLite in particular) fails with
`SQLITE_CANTOPEN` that looks like database corruption.

Open <http://127.0.0.1:8080> — it redirects to `/login`, because the Portal Behavior
extension makes `/` an entry point (guests → login, customers → dashboard) to match the
client's portal. Disable that extension to get the storefront homepage back.

## Catalogue

A fresh database has no products, and core only shows the **Store** menu when at least one
category exists — so an empty install correctly renders no Store dropdown. Seed the real
catalogue with `scripts/seed-catalogue.php` (needs a configured ProxyPanel server) or create
a category and product in the admin.

## Notes

- `APP_ENV=local` enables Laravel Debugbar, which injects view names such as
  `components.navigation.index` into the page as profiling JSON. That is Debugbar's payload,
  not stray output.
- The `proxy` theme is plain CSS with no build step, so `npm run build` is not needed to see
  it. The pre-compiled bundles under `public/<theme>/assets` cover the rest.
- To go back to SQLite, restore the `DB_*` block (a `.env.sqlite-backup` is written the first
  time this switch is made).
