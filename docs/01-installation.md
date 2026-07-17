# Installation & Deployment (Debian 13)

This guide covers a production install on **Debian 13 (Trixie)**. The automated script does all
of it; the manual steps are documented for transparency and troubleshooting.

## Requirements

- Debian 13 server (root/sudo), a domain pointed at it (A/AAAA record).
- PHP **8.3+** (8.4 recommended), Composer, Node 20+, MariaDB 10.6+/MySQL 8, Redis, Nginx.

## Automated install (recommended)

From the repository on the server:

```bash
sudo bash scripts/install-debian13.sh
# or non-interactively:
sudo DOMAIN=billing.example.com ADMIN_EMAIL=you@example.com DB_PASSWORD='STRONGPASS' \
     bash scripts/install-debian13.sh
```

The script is **idempotent** (safe to re-run) and performs:

1. Base packages (Nginx, MariaDB, Redis, Certbot, git).
2. PHP 8.4 + required extensions (mysql, redis, mbstring, xml, curl, zip, gd, intl, bcmath).
3. Composer + Node 20.
4. Database + app DB user creation.
5. App dependency install (`composer install --no-dev`, `npm ci && npm run build`).
6. `.env` generation, `key:generate`, `migrate --seed`, `storage:link`, `optimize`.
7. File permissions for `www-data`.
8. Nginx vhost + **HTTPS via Let's Encrypt** (auto-redirect).
9. **systemd** units: `paymenter-queue.service` (queue worker) and
   `paymenter-scheduler.timer` (runs `schedule:run` every minute).
10. Nightly **backup** cron (`03:30`) via `scripts/backup.sh`.

After it finishes, open `https://YOUR-DOMAIN` and complete the admin setup.

## Manual install (summary)

```bash
cp .env.example .env         # set APP_URL, DB_*, REDIS/QUEUE
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan optimize
```

- **Queue:** run `php artisan queue:work` under a process supervisor (systemd unit provided).
- **Scheduler:** add cron `* * * * * php /var/www/paymenter/artisan schedule:run` (or the
  provided systemd timer).

## Services & operations

| Task | Command |
|---|---|
| Queue status | `systemctl status paymenter-queue` |
| Scheduler timers | `systemctl list-timers paymenter-scheduler.timer` |
| Restart queue after deploy | `systemctl restart paymenter-queue` |
| App logs | `tail -f storage/logs/laravel-*.log` |

## Backup & restore

```bash
# Manual backup (cron installs this to run nightly):
sudo DB_PASSWORD='...' bash scripts/backup.sh
# Restore:
sudo DB_PASSWORD='...' bash scripts/restore.sh /var/backups/paymenter/db-YYYYMMDD-HHMMSS.sql.gz \
     /var/backups/paymenter/files-YYYYMMDD-HHMMSS.tar.gz
```

Backups (DB dump + `.env`/`storage`) land in `/var/backups/paymenter`, pruned after 14 days.
**Store copies off-server** (S3/rsync) for real disaster recovery.

## HTTPS renewal

Certbot installs a renewal timer automatically. Verify with `systemctl list-timers | grep certbot`
and test with `certbot renew --dry-run`.

## Troubleshooting

| Symptom | Fix |
|---|---|
| 502 Bad Gateway | Check the PHP-FPM socket path in the Nginx vhost matches `/run/php/php8.4-fpm.sock` |
| Certbot fails | DNS not pointed yet — re-run `certbot --nginx -d YOUR-DOMAIN` after DNS propagates |
| Jobs not processing | `systemctl status paymenter-queue`; ensure Redis is up |
| Permission errors | `chown -R www-data:www-data /var/www/paymenter && chmod -R 775 storage bootstrap/cache` |
| **Blank page, CSS/JS 404** (theme assets `app-*.css/js`) | Asset URLs must match the URL you open. Set `APP_URL` **and** `ASSET_URL` to the exact scheme+host+port (e.g. `https://billing.example.com`), then `php artisan config:cache`. In local Docker this is preset in `docker-compose.dev.yml` (`ASSET_URL=http://localhost:8080`); a reverse proxy that strips the port causes port-less asset URLs otherwise. |
