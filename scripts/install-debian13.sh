#!/usr/bin/env bash
#
# install-debian13.sh — Automated Paymenter deployment for Debian 13 (Trixie).
#
# Provisions: PHP 8.4 (+extensions), Composer, MariaDB, Redis, Nginx, HTTPS
# (Let's Encrypt), the app itself, the queue worker + scheduler (systemd), and
# a nightly backup cron.
#
# Idempotent: safe to re-run. Prompts for the few things it cannot infer, or
# reads them from environment variables / a --env-file.
#
# Usage:
#   sudo bash scripts/install-debian13.sh
#   sudo DOMAIN=billing.example.com ADMIN_EMAIL=you@example.com \
#        DB_PASSWORD='...' bash scripts/install-debian13.sh
#
set -Eeuo pipefail

# ── Helpers ─────────────────────────────────────────────────────────────────
log()  { printf '\033[1;34m[+]\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m[!]\033[0m %s\n' "$*"; }
die()  { printf '\033[1;31m[x]\033[0m %s\n' "$*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || die "Please run as root (sudo)."
[[ -r /etc/os-release ]] && . /etc/os-release
[[ "${ID:-}" == "debian" ]] || warn "This script targets Debian 13; detected '${ID:-unknown}'. Continuing."

# ── Configuration (env-overridable) ─────────────────────────────────────────
APP_DIR="${APP_DIR:-/var/www/paymenter}"
APP_USER="${APP_USER:-www-data}"
PHP_VERSION="${PHP_VERSION:-8.4}"
DB_NAME="${DB_NAME:-paymenter}"
DB_USER="${DB_USER:-paymenter}"
REPO_URL="${REPO_URL:-}"          # optional: git URL to deploy from
BRANCH="${BRANCH:-main}"

prompt() { # prompt VAR "Question" [silent]
  local __var=$1 __q=$2 __silent=${3:-}
  local __cur="${!__var:-}"
  [[ -n "$__cur" ]] && return 0
  if [[ -n "$__silent" ]]; then read -rsp "$__q: " "$__var"; echo; else read -rp "$__q: " "$__var"; fi
}

prompt DOMAIN      "Domain (e.g. billing.example.com)"
prompt ADMIN_EMAIL "Admin email (for Let's Encrypt & app)"
prompt DB_PASSWORD "Database password for user '$DB_USER'" silent
[[ -n "${DB_PASSWORD:-}" ]] || die "DB_PASSWORD is required."

# ── 1. Base packages ────────────────────────────────────────────────────────
log "Updating apt and installing base packages…"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y ca-certificates curl gnupg lsb-release git unzip \
  nginx mariadb-server redis-server certbot python3-certbot-nginx

# ── 2. PHP $PHP_VERSION ─────────────────────────────────────────────────────
# Debian 13 ships PHP 8.4 in its repos. If a different minor is requested, add sury.
if ! command -v "php${PHP_VERSION%.*}" >/dev/null 2>&1 && ! command -v php >/dev/null 2>&1; then
  log "Installing PHP ${PHP_VERSION}…"
fi
apt-get install -y \
  "php${PHP_VERSION}" "php${PHP_VERSION}-fpm" "php${PHP_VERSION}-cli" \
  "php${PHP_VERSION}-mysql" "php${PHP_VERSION}-redis" "php${PHP_VERSION}-mbstring" \
  "php${PHP_VERSION}-xml" "php${PHP_VERSION}-curl" "php${PHP_VERSION}-zip" \
  "php${PHP_VERSION}-gd" "php${PHP_VERSION}-intl" "php${PHP_VERSION}-bcmath" \
  || apt-get install -y php php-fpm php-cli php-mysql php-redis php-mbstring \
     php-xml php-curl php-zip php-gd php-intl php-bcmath

PHP_FPM_SOCK="/run/php/php${PHP_VERSION}-fpm.sock"
[[ -S "$PHP_FPM_SOCK" ]] || PHP_FPM_SOCK="$(ls /run/php/php*-fpm.sock 2>/dev/null | head -1 || true)"

# ── 3. Composer & Node ──────────────────────────────────────────────────────
if ! command -v composer >/dev/null 2>&1; then
  log "Installing Composer…"
  curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
  php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
  rm -f /tmp/composer-setup.php
fi
if ! command -v node >/dev/null 2>&1; then
  log "Installing Node.js 20…"
  curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
  apt-get install -y nodejs
fi

# ── 4. Database ─────────────────────────────────────────────────────────────
log "Configuring MariaDB…"
systemctl enable --now mariadb
mysql --protocol=socket <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

systemctl enable --now redis-server

# ── 5. Application code ─────────────────────────────────────────────────────
if [[ -n "$REPO_URL" && ! -d "$APP_DIR/.git" ]]; then
  log "Cloning application from $REPO_URL…"
  git clone --branch "$BRANCH" "$REPO_URL" "$APP_DIR"
elif [[ ! -d "$APP_DIR" ]]; then
  # Deploy from the directory this script lives in (repo already on disk).
  SRC_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
  log "Copying application from $SRC_DIR to $APP_DIR…"
  mkdir -p "$APP_DIR"
  cp -a "$SRC_DIR/." "$APP_DIR/"
else
  log "App directory already present at $APP_DIR (skipping fetch)."
fi

cd "$APP_DIR"

log "Installing PHP & JS dependencies and building assets…"
sudo -u "$APP_USER" -H composer install --no-dev --optimize-autoloader --no-interaction \
  || composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build

# ── 6. Environment ──────────────────────────────────────────────────────────
if [[ ! -f .env ]]; then
  log "Creating .env…"
  cp .env.example .env
  sed -i \
    -e "s|^APP_ENV=.*|APP_ENV=production|" \
    -e "s|^APP_DEBUG=.*|APP_DEBUG=false|" \
    -e "s|^APP_URL=.*|APP_URL=https://${DOMAIN}|" \
    -e "s|^DB_CONNECTION=.*|DB_CONNECTION=mariadb|" \
    -e "s|^DB_HOST=.*|DB_HOST=127.0.0.1|" \
    -e "s|^DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|" \
    -e "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" \
    -e "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|" \
    .env
  grep -q '^APP_URL=' .env || echo "APP_URL=https://${DOMAIN}" >> .env
  php artisan key:generate --force
fi

log "Running migrations & seeders…"
php artisan migrate --force --seed
php artisan storage:link || true
php artisan optimize

# ── 7. Permissions ──────────────────────────────────────────────────────────
log "Setting permissions…"
chown -R "$APP_USER":"$APP_USER" "$APP_DIR"
find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type d -exec chmod 775 {} \;

# ── 8. Nginx + HTTPS ────────────────────────────────────────────────────────
log "Configuring Nginx…"
cat > "/etc/nginx/sites-available/paymenter.conf" <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN};
    root ${APP_DIR}/public;
    index index.php;

    charset utf-8;
    client_max_body_size 20M;

    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
    error_page 404 /index.php;

    location ~ \.php\$ {
        fastcgi_pass unix:${PHP_FPM_SOCK};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }
    location ~ /\.(?!well-known).* { deny all; }
}
NGINX
ln -sf /etc/nginx/sites-available/paymenter.conf /etc/nginx/sites-enabled/paymenter.conf
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

log "Requesting HTTPS certificate via Let's Encrypt…"
certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "$ADMIN_EMAIL" --redirect \
  || warn "Certbot failed (DNS not pointed yet?). Re-run: certbot --nginx -d $DOMAIN"

# ── 9. Queue worker + scheduler (systemd) ───────────────────────────────────
log "Installing systemd units for queue worker and scheduler…"
cat > /etc/systemd/system/paymenter-queue.service <<UNIT
[Unit]
Description=Paymenter queue worker
After=network.target mariadb.service redis-server.service

[Service]
User=${APP_USER}
Restart=always
RestartSec=3
WorkingDirectory=${APP_DIR}
ExecStart=/usr/bin/php ${APP_DIR}/artisan queue:work --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
UNIT

cat > /etc/systemd/system/paymenter-scheduler.service <<UNIT
[Unit]
Description=Paymenter scheduler (artisan schedule:run)
After=network.target

[Service]
User=${APP_USER}
WorkingDirectory=${APP_DIR}
ExecStart=/usr/bin/php ${APP_DIR}/artisan schedule:run
UNIT

cat > /etc/systemd/system/paymenter-scheduler.timer <<UNIT
[Unit]
Description=Run Paymenter scheduler every minute

[Timer]
OnCalendar=*-*-* *:*:00
AccuracySec=1s

[Install]
WantedBy=timers.target
UNIT

systemctl daemon-reload
systemctl enable --now paymenter-queue.service
systemctl enable --now paymenter-scheduler.timer

# ── 10. Nightly backups ─────────────────────────────────────────────────────
log "Installing nightly backup cron…"
install -m 0755 "${APP_DIR}/scripts/backup.sh" /usr/local/bin/paymenter-backup 2>/dev/null || true
cat > /etc/cron.d/paymenter-backup <<CRON
# Nightly Paymenter backup at 03:30
30 3 * * * root APP_DIR=${APP_DIR} DB_NAME=${DB_NAME} DB_USER=${DB_USER} DB_PASSWORD='${DB_PASSWORD}' /usr/local/bin/paymenter-backup >> /var/log/paymenter-backup.log 2>&1
CRON

log "Done. Visit https://${DOMAIN} to finish setup."
log "Queue:     systemctl status paymenter-queue"
log "Scheduler: systemctl list-timers paymenter-scheduler.timer"
