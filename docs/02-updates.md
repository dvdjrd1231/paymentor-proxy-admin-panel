# Updating Paymenter (without losing customizations)

Because this project **does not modify Paymenter core**, upstream updates apply cleanly. Custom
work lives only in `extensions/`, `themes/`, `scripts/`, `docs/`, and config.

## Update procedure

```bash
cd /var/www/paymenter
sudo bash scripts/backup.sh                 # 1. always back up first
php artisan down                            # 2. maintenance mode

git fetch origin && git merge origin/main   # 3. pull our repo (which tracks upstream)
composer install --no-dev --optimize-autoloader
npm ci && npm run build

php artisan migrate --force                 # 4. new migrations (core + extensions)
php artisan optimize:clear && php artisan optimize
systemctl restart paymenter-queue           # 5. restart the worker

php artisan up                              # 6. exit maintenance mode
```

## Tracking upstream Paymenter

Upstream is vendored into this repo. To pull a newer Paymenter release:

```bash
git remote add upstream https://github.com/paymenter/paymenter.git   # once
git fetch upstream
git merge upstream/main        # resolve conflicts (should be limited to composer.lock etc.)
```

Any unavoidable core touch is listed in `docs/CORE-TOUCHPOINTS.md` with re-apply notes — check it
after every upstream merge.

## After updating

- Re-run `php artisan app:extension:enable ...` if an extension was disabled by the update.
- Verify webhooks still resolve (`php artisan route:list | grep extensions`).
- Smoke-test a payment and a provisioning action in staging before production.
