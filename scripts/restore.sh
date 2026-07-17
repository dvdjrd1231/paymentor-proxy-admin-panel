#!/usr/bin/env bash
#
# restore.sh — Restore a Paymenter backup produced by backup.sh.
# Usage: DB_PASSWORD='...' bash restore.sh <db-YYYYMMDD-HHMMSS.sql.gz> [files-….tar.gz]
set -Eeuo pipefail

APP_DIR="${APP_DIR:-/var/www/paymenter}"
DB_NAME="${DB_NAME:-paymenter}"
DB_USER="${DB_USER:-paymenter}"
DB_PASSWORD="${DB_PASSWORD:-}"

DB_DUMP="${1:-}"
FILES_ARCHIVE="${2:-}"
[[ -n "$DB_DUMP" && -f "$DB_DUMP" ]] || { echo "Usage: restore.sh <db.sql.gz> [files.tar.gz]" >&2; exit 1; }

read -rp "This will OVERWRITE database '$DB_NAME'. Type 'yes' to continue: " ok
[[ "$ok" == "yes" ]] || { echo "Aborted."; exit 1; }

echo "[+] Restoring database from $DB_DUMP…"
gunzip -c "$DB_DUMP" | mariadb --host=127.0.0.1 --user="$DB_USER" --password="$DB_PASSWORD" "$DB_NAME"

if [[ -n "$FILES_ARCHIVE" && -f "$FILES_ARCHIVE" ]]; then
  echo "[+] Restoring files from $FILES_ARCHIVE…"
  tar -xzf "$FILES_ARCHIVE" -C "$APP_DIR"
fi

echo "[+] Clearing caches…"
( cd "$APP_DIR" && php artisan optimize:clear )
echo "[+] Restore complete."
