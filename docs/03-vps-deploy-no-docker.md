# VPS deployment without Docker

Manual deployment onto a fresh Debian 13 / Ubuntu LTS server. For the scripted route see
[`01-installation.md`](01-installation.md); this is the step-by-step equivalent.

> **You usually do not need the SQL export.** `php artisan migrate --seed` builds the whole
> schema and the default settings. Import a dump only if you want to carry over the
> configuration prepared locally. See § Database export below.

## 1. Server packages

```bash
sudo apt update
sudo apt install -y nginx mariadb-server redis-server git unzip curl \
    php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml \
    php8.3-curl php8.3-zip php8.3-gd php8.3-intl php8.3-bcmath php8.3-redis
```

PHP **8.3 or newer** is required — 8.2 will not install the dependencies.
On Ubuntu you may need `ppa:ondrej/php` for 8.3.

## 2. Database

```bash
sudo mysql -e "CREATE DATABASE paymenter CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'paymenter'@'localhost' IDENTIFIED BY 'CHANGE-ME';"
sudo mysql -e "GRANT ALL ON paymenter.* TO 'paymenter'@'localhost'; FLUSH PRIVILEGES;"
```

## 3. Application

```bash
sudo mkdir -p /var/www/paymenter && sudo chown -R $USER /var/www/paymenter
cd /var/www/paymenter
# copy the source here (git clone, rsync or scp)

composer install --no-dev --optimize-autoloader
npm ci && npm run build

cp .env.example .env
```

Edit `.env`:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://billing.example.com

DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_DATABASE=paymenter
DB_USERNAME=paymenter
DB_PASSWORD=CHANGE-ME

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

```bash
php artisan key:generate
php artisan migrate --seed
php artisan storage:link

# First admin (role 1 = admin, created by the seeder)
php artisan app:user:create Admin You you@example.com 'StrongPassword!' 1
```

### The one setting that catches people out

Paymenter reads its canonical URL from the **`app_url` database setting**, not from
`.env APP_URL`. If they disagree, every request redirects to the setting's host:

```bash
php artisan app:settings:change app_url "https://billing.example.com"
php artisan config:cache
```

## 4. Permissions

```bash
sudo chown -R www-data:www-data /var/www/paymenter
sudo chmod -R 755 /var/www/paymenter
sudo chmod -R 775 /var/www/paymenter/storage /var/www/paymenter/bootstrap/cache
```

## 5. Nginx + HTTPS

```nginx
server {
    listen 80;
    server_name billing.example.com;
    root /var/www/paymenter/public;
    index index.php;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
    location ~ /\.(?!well-known).* { deny all; }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/paymenter /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d billing.example.com
```

## 6. Queue worker + scheduler

Provisioning runs on the queue — without a worker, **services are never created**.

`/etc/systemd/system/paymenter-queue.service`:

```ini
[Unit]
Description=Paymenter queue worker
After=network.target

[Service]
User=www-data
Restart=always
ExecStart=/usr/bin/php /var/www/paymenter/artisan queue:work --tries=3 --timeout=120

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable --now paymenter-queue
sudo crontab -u www-data -e
# * * * * * php /var/www/paymenter/artisan schedule:run >> /dev/null 2>&1
```

The scheduler drives invoice generation, suspension and termination — without it, billing
automation does not run.

## 7. Enable extensions and configure

In `/admin`:

1. **Extensions** — enable `ProxyPanel`, `CoinPayments`, `Binance`, `Cryptomus`, and the
   `Others/*` modules. Enabling runs each extension's `installed()` hook, which creates its
   tables.
2. **Settings → Theme** — set to `proxy`.
3. **Servers** — create a ProxyPanel server: **Panel API URL**, **Panel Token**,
   **Callback Secret**.
4. **Gateways** — create and configure each payment gateway with live credentials.

## 8. Backups

```bash
sudo DB_PASSWORD='CHANGE-ME' bash scripts/backup.sh
sudo crontab -e
# 30 3 * * * DB_PASSWORD='CHANGE-ME' bash /var/www/paymenter/scripts/backup.sh
```

---

## Database export

`scripts/export-database.php` dumps the local database as MySQL/MariaDB-compatible SQL.

```bash
php scripts/export-database.php --clean   # database/export/paymenter-clean.sql
php scripts/export-database.php           # database/export/paymenter-full.sql
```

| File | Contains | Use it when |
|---|---|---|
| `paymenter-clean.sql` | Settings, roles, currency, notification templates, custom properties, product/plan/price catalogue, extension registry | Moving your prepared configuration to a real server |
| `paymenter-full.sql` | Everything, **including local test data** — 21 test services, test invoices and transactions, test users | Reproducing the local environment for debugging only |

**Use `--clean` for a real deployment.** The full export contains services pointing at the
mock panel on `127.0.0.1:9000`, test invoices, and test accounts.

The clean export deliberately omits **users** and all **Server/Gateway credentials**: those
are per-deployment secrets, and exporting users would carry a known password hash into
production. Create the admin with `app:user:create` and configure gateways in the admin
panel.

### Import order

```bash
php artisan migrate --seed        # 1. core schema
# 2. enable the extensions in Admin -> Extensions (creates their tables)
mysql -u paymenter -p paymenter < paymenter-clean.sql   # 3. data
php artisan config:cache
```

Step 2 matters: `gateway_rules`, `payment_fee_rules`, `provisioning_operations`,
`canned_responses` and `ticket_notes` are created by each extension's `installed()` hook,
not by `artisan migrate`. Importing before then fails on those tables.

The dump sets `NO_BACKSLASH_ESCAPES` and escapes quotes by doubling them, which is standard
SQL — do not strip that `SET` line, or values containing backslashes will be mangled.

Both files are verified by importing them into a freshly migrated database before release.
