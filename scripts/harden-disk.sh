#!/usr/bin/env bash
#
# Put permanent limits on the things that fill this server's disk.
#
# One-off cleanups do not hold. On a 4.8GB volume the journal had grown to 96MB and the apt
# cache to 526MB before anyone looked, and Docker's default json-file log driver has **no
# rotation at all** — a container that logs steadily will fill the disk and take the site
# down with it. This installs the caps and a weekly sweep so it stops being something a
# person has to remember.
#
# What it configures:
#
#   Docker      json-file logs capped at 10MB × 3 per container. Note the compose stack
#               sets this per service as well, because a daemon reload silently discards
#               log-opts and only a full daemon restart applies them.
#   journald    capped at 200MB, and vacuumed to that now
#   Laravel     log files older than 14 days removed by the weekly sweep
#   apt         cache cleaned weekly
#   Docker      dangling images and build cache pruned weekly
#   Alert       a daily check that warns while there is still time to act
#
# Safe to re-run: every step is idempotent, and it never touches named volumes, the database,
# or any image an existing container depends on.
#
#   sudo ./scripts/harden-disk.sh --check      # report only, change nothing
#   sudo ./scripts/harden-disk.sh
set -Eeuo pipefail

CHECK_ONLY=0
for arg in "$@"; do
  case "$arg" in --check|--dry-run) CHECK_ONLY=1 ;; esac
done

log()  { printf '\033[1;34m[+]\033[0m %s\n' "$*"; }
ok()   { printf '  \033[1;32m✓\033[0m %s\n' "$*"; }
note() { printf '  \033[1;33m!\033[0m %s\n' "$*"; }
die()  { printf '\033[1;31m[x]\033[0m %s\n' "$*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || die "run as root (sudo)."

ALERT_THRESHOLD="${ALERT_THRESHOLD:-85}"
JOURNAL_CAP="${JOURNAL_CAP:-200M}"
LOG_RETENTION_DAYS="${LOG_RETENTION_DAYS:-14}"
APP_DIR="${APP_DIR:-/opt/paymentor-proxy-admin-panel}"

usage() { df -Pm / | awk 'NR==2 {printf "%s used of %s (%s), %sMB free", $3, $2, $5, $4}'; }

log "Disk before: $(usage)"

if (( CHECK_ONLY )); then
  log "Current state"
  [[ -f /etc/docker/daemon.json ]] && grep -q max-size /etc/docker/daemon.json 2>/dev/null \
    && ok "docker log rotation configured" || note "docker logs are UNBOUNDED (no max-size)"
  grep -qE '^SystemMaxUse=' /etc/systemd/journald.conf 2>/dev/null \
    && ok "journal cap set ($(grep -E '^SystemMaxUse=' /etc/systemd/journald.conf))" || note "journal has no cap"
  [[ -x /etc/cron.weekly/paymenter-disk-sweep ]] && ok "weekly sweep installed" || note "no weekly sweep"
  [[ -x /etc/cron.daily/paymenter-disk-alert ]]  && ok "daily alert installed"  || note "no daily alert"
  echo
  log "--check specified: nothing was changed."
  exit 0
fi

# ── 1. Docker log rotation ──────────────────────────────────────────────────
# Without this a single chatty container can consume the whole volume. The setting applies
# to containers created after the daemon reloads, so existing ones keep their current
# (unbounded) logs until they are next recreated — `docker compose up -d --force-recreate`.
log "Docker log rotation"
if [[ -f /etc/docker/daemon.json ]] && grep -q '"max-size"' /etc/docker/daemon.json; then
  ok "already configured"
else
  if [[ -f /etc/docker/daemon.json ]] && command -v python3 >/dev/null 2>&1; then
    # Merge rather than overwrite — the file may hold registry or storage settings.
    python3 - <<'PY'
import json, pathlib
p = pathlib.Path('/etc/docker/daemon.json')
try:
    cfg = json.loads(p.read_text() or '{}')
except Exception:
    cfg = {}
cfg['log-driver'] = 'json-file'
cfg.setdefault('log-opts', {}).update({'max-size': '10m', 'max-file': '3'})
p.write_text(json.dumps(cfg, indent=2) + '\n')
PY
    ok "merged into the existing /etc/docker/daemon.json"
  else
    mkdir -p /etc/docker
    cat > /etc/docker/daemon.json <<'JSON'
{
  "log-driver": "json-file",
  "log-opts": { "max-size": "10m", "max-file": "3" }
}
JSON
    ok "wrote /etc/docker/daemon.json (10MB × 3 per container)"
  fi

  # A reload is NOT enough, and quietly so: dockerd accepts `log-driver` on SIGHUP and drops
  # `log-opts` without complaint, so the cap looks configured while nothing is capped. Only
  # a full daemon restart applies it — and that stops every running container, which is not
  # something this script should do to a live site on its own.
  systemctl reload docker 2>/dev/null || true
  note "daemon-level log-opts need a full \`systemctl restart docker\` — a reload silently"
  note "drops them. That restarts every container, so do it during a maintenance window."
  note "The compose stack does not depend on this: docker-compose.vps.yml sets the same"
  note "limit per service, which applies as soon as containers are recreated."
fi

# ── 2. Journal cap ──────────────────────────────────────────────────────────
log "Journal cap"
if grep -qE '^SystemMaxUse=' /etc/systemd/journald.conf; then
  sed -i "s/^SystemMaxUse=.*/SystemMaxUse=${JOURNAL_CAP}/" /etc/systemd/journald.conf
else
  printf 'SystemMaxUse=%s\n' "$JOURNAL_CAP" >> /etc/systemd/journald.conf
fi
systemctl restart systemd-journald 2>/dev/null || true
journalctl --vacuum-size="$JOURNAL_CAP" >/dev/null 2>&1 || true
ok "journal capped at ${JOURNAL_CAP}"

# ── 3. Weekly sweep ─────────────────────────────────────────────────────────
# Everything here is regenerable: package archives re-download, dangling images rebuild,
# and old application logs are past the window anyone reads. Named volumes, the database
# and in-use images are never touched.
log "Weekly sweep"
cat > /etc/cron.weekly/paymenter-disk-sweep <<SWEEP
#!/bin/sh
# Installed by scripts/harden-disk.sh. Reclaims only regenerable space.
apt-get clean >/dev/null 2>&1
journalctl --vacuum-size=${JOURNAL_CAP} >/dev/null 2>&1

# Dangling images and build cache only — never \`-a\`, which would remove images that
# stopped containers still need.
docker image prune -f >/dev/null 2>&1
docker builder prune -f >/dev/null 2>&1

# Application logs past the retention window.
find ${APP_DIR}/storage/logs -name 'laravel-*.log' -mtime +${LOG_RETENTION_DAYS} -delete 2>/dev/null

logger -t paymenter-disk "weekly sweep done; root now \$(df -Ph / | awk 'NR==2 {print \$5}')"
SWEEP
chmod +x /etc/cron.weekly/paymenter-disk-sweep
ok "installed /etc/cron.weekly/paymenter-disk-sweep"

# ── 4. Daily alert ──────────────────────────────────────────────────────────
# A full disk takes the site down with no warning. This warns while there is still room to
# act, using the mail transport the platform already has.
log "Daily alert"
cat > /etc/cron.daily/paymenter-disk-alert <<ALERT
#!/bin/sh
# Installed by scripts/harden-disk.sh.
USED=\$(df -P / | awk 'NR==2 {gsub("%","",\$5); print \$5}')
[ "\$USED" -lt ${ALERT_THRESHOLD} ] && exit 0

REPORT="Disk on \$(hostname) is \${USED}% full (threshold ${ALERT_THRESHOLD}%).

\$(df -h /)

Largest directories:
\$(du -sh /var/lib/docker /var/lib/containerd /var/log /var/cache 2>/dev/null | sort -rh)
"

logger -t paymenter-disk "WARNING: root filesystem \${USED}% full"
command -v mail >/dev/null 2>&1 && echo "\$REPORT" | mail -s "Disk \${USED}% full on \$(hostname)" root
exit 0
ALERT
chmod +x /etc/cron.daily/paymenter-disk-alert
ok "installed /etc/cron.daily/paymenter-disk-alert (warns above ${ALERT_THRESHOLD}%)"

# ── 5. Reclaim now ──────────────────────────────────────────────────────────
log "Reclaiming what is safe today"
apt-get clean >/dev/null 2>&1 && ok "apt cache cleaned"
docker image prune -f >/dev/null 2>&1 && ok "dangling images pruned"
find "${APP_DIR}/storage/logs" -name 'laravel-*.log' -mtime "+${LOG_RETENTION_DAYS}" -delete 2>/dev/null || true
ok "application logs older than ${LOG_RETENTION_DAYS} days removed"

echo
log "Disk after: $(usage)"
log "Done. Re-run any time; every step is idempotent."
