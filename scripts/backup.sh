#!/usr/bin/env bash
#
# backup.sh — Back up the Paymenter database and critical files.
# Env: APP_DIR, DB_NAME, DB_USER, DB_PASSWORD, BACKUP_DIR (default /var/backups/paymenter),
#      RETENTION_DAYS (default 14).
set -Eeuo pipefail

APP_DIR="${APP_DIR:-/var/www/paymenter}"
DB_NAME="${DB_NAME:-paymenter}"
DB_USER="${DB_USER:-paymenter}"
DB_PASSWORD="${DB_PASSWORD:-}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/paymenter}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
STAMP="$(date +%Y%m%d-%H%M%S)"

mkdir -p "$BACKUP_DIR"

echo "[+] Dumping database $DB_NAME…"
mariadb-dump --single-transaction --quick --host=127.0.0.1 \
  --user="$DB_USER" --password="$DB_PASSWORD" "$DB_NAME" \
  | gzip > "$BACKUP_DIR/db-$STAMP.sql.gz"

echo "[+] Archiving .env and storage…"
tar -czf "$BACKUP_DIR/files-$STAMP.tar.gz" \
  -C "$APP_DIR" .env storage/app storage/logs 2>/dev/null || true

echo "[+] Pruning backups older than ${RETENTION_DAYS} days…"
find "$BACKUP_DIR" -type f -mtime +"$RETENTION_DAYS" -delete

echo "[+] Backup complete: $BACKUP_DIR (db-$STAMP.sql.gz, files-$STAMP.tar.gz)"
