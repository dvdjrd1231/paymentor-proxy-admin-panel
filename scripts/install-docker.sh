#!/usr/bin/env bash
#
# Install the whole platform with Docker, in one command.
#
# This is the recommended way to stand the project up. Everything it needs — PHP, MariaDB,
# Redis, the queue worker and the scheduler — comes from the images, so the host only needs
# Docker itself. Compare scripts/install-debian13.sh, which provisions those same pieces
# directly onto a Debian host and therefore has far more that can differ between machines.
#
#   ./scripts/install-docker.sh --check                 # verify the host, change nothing
#   PUBLIC_URL=http://203.0.113.10 ./scripts/install-docker.sh
#
# Every answer can come from the environment, so it runs unattended:
#
#   PUBLIC_URL      what you type in the browser. Generated links and assets are built
#                   from it, so http://IP and https://domain are not interchangeable.
#   DB_PASSWORD     generated if unset, and written to the compose env file.
#   ADMIN_EMAIL     admin login. ADMIN_PASSWORD generated if unset.
#   ADMIN_PASSWORD
#   HTTP_PORT       host port to publish (default 80). Use another to run a second stack
#                   alongside an existing one.
#   PROJECT         compose project name (default paymenter). Change it for a second stack.
#
# Re-running against a live stack is safe: it brings the stack up to date rather than
# wiping it, and skips creating the admin if one already exists.
set -Eeuo pipefail

CHECK_ONLY=0
for arg in "$@"; do
  case "$arg" in
    --check|--dry-run) CHECK_ONLY=1 ;;
    -h|--help) sed -n '2,30p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
  esac
done

log()  { printf '\033[1;34m[+]\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m[!]\033[0m %s\n' "$*"; }
die()  { printf '\033[1;31m[x]\033[0m %s\n' "$*" >&2; exit 1; }
ok()   { printf '  \033[1;32m✓\033[0m %s\n' "$*"; }
bad()  { printf '  \033[1;31m✗\033[0m %s\n' "$*"; FAIL=1; }
note() { printf '  \033[1;33m!\033[0m %s\n' "$*"; }

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE_FILE="$ROOT/docker-compose.vps.yml"
ENV_FILE="$ROOT/.env.docker"

PROJECT="${PROJECT:-paymenter}"
HTTP_PORT="${HTTP_PORT:-80}"

# Reuse the answers from a previous run so re-running does not need them again.
if [[ -f "$ENV_FILE" ]]; then
  # shellcheck disable=SC1090
  set -a; source "$ENV_FILE"; set +a
fi

# ── Preflight ───────────────────────────────────────────────────────────────
FAIL=0
log "Preflight checks"

if command -v docker >/dev/null 2>&1; then
  ok "docker present ($(docker --version | cut -d, -f1))"
else
  bad "docker not installed — see https://docs.docker.com/engine/install/"
fi

if docker compose version >/dev/null 2>&1; then
  ok "docker compose v2 present"
elif command -v docker-compose >/dev/null 2>&1; then
  note "only the legacy docker-compose is present; v2 (\`docker compose\`) is expected"
else
  bad "docker compose not available"
fi

if docker info >/dev/null 2>&1; then
  ok "docker daemon is running and reachable"
else
  bad "cannot talk to the docker daemon — is it running, and are you in the docker group?"
fi

# Only a conflict on the port we intend to publish matters.
if ss -lnt 2>/dev/null | awk '{print $4}' | grep -qE "[:.]${HTTP_PORT}$"; then
  if docker ps --format '{{.Ports}}' 2>/dev/null | grep -q ":${HTTP_PORT}->"; then
    note "port ${HTTP_PORT} is held by an existing container — this run will update that stack"
  else
    bad "port ${HTTP_PORT} is already in use by something else — set HTTP_PORT to a free port"
  fi
else
  ok "port ${HTTP_PORT} is free"
fi

# The images and the database volume need room; the app itself is mounted from here.
FREE_MB=$(df -Pm "$ROOT" | awk 'NR==2 {print $4}')
if (( FREE_MB >= 3000 )); then ok "disk: ${FREE_MB}MB free"
elif (( FREE_MB >= 1200 )); then note "disk: ${FREE_MB}MB free — enough, 3GB recommended"
else bad "disk: ${FREE_MB}MB free — the images alone need about 1.5GB"; fi

for f in docker-compose.vps.yml extensions themes lang app database/migrations scripts; do
  [[ -e "$ROOT/$f" ]] && ok "found $f" || bad "missing $f in $ROOT"
done

if [[ -n "${PUBLIC_URL:-}" ]]; then
  [[ "$PUBLIC_URL" =~ ^https?:// ]] && ok "PUBLIC_URL is ${PUBLIC_URL}" \
    || bad "PUBLIC_URL must start with http:// or https:// (got '${PUBLIC_URL}')"
else
  bad "PUBLIC_URL is not set — e.g. PUBLIC_URL=http://203.0.113.10"
fi

if (( CHECK_ONLY )); then
  (( FAIL )) && die "Preflight failed. Fix the items marked ✗ and re-run."
  log "--check specified: the host is ready and nothing was changed."
  exit 0
fi

(( FAIL )) && die "Preflight failed. Fix the items marked ✗ and re-run."

# ── Settings ────────────────────────────────────────────────────────────────
# A missing password is generated rather than prompted, so an unattended run still ends
# with a working install instead of hanging on a question nobody is there to answer.
# `head -c` closes the pipe as soon as it has enough, which kills `tr` with SIGPIPE. Under
# `set -o pipefail` that is a non-zero status and `set -e` would abort the install, so the
# pipeline runs in a subshell with pipefail off.
gen() {
  local n="${1:-24}" out
  out="$(set +o pipefail; LC_ALL=C tr -dc 'A-Za-z0-9' < /dev/urandom 2>/dev/null | head -c "$n")"
  printf '%s' "$out"
}

DB_PASSWORD="${DB_PASSWORD:-$(gen 28)}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.com}"

# Note whether the password was supplied before defaulting it, so the "save this now"
# warning only appears when there is actually something unrecoverable to save.
GENERATED_ADMIN_PASSWORD=0
if [[ -z "${ADMIN_PASSWORD:-}" ]]; then
  ADMIN_PASSWORD="$(gen 18)"
  GENERATED_ADMIN_PASSWORD=1
fi

umask 077
cat > "$ENV_FILE" <<EOF
# Written by scripts/install-docker.sh. Keep it out of version control — it holds secrets.
PUBLIC_URL=${PUBLIC_URL}
DB_PASSWORD=${DB_PASSWORD}
ADMIN_EMAIL=${ADMIN_EMAIL}
HTTP_PORT=${HTTP_PORT}
PROJECT=${PROJECT}
EOF
umask 022
log "Settings written to ${ENV_FILE} (permissions 600)"

compose() {
  PUBLIC_URL="$PUBLIC_URL" DB_PASSWORD="$DB_PASSWORD" HTTP_PORT="$HTTP_PORT" \
    docker compose -p "$PROJECT" -f "$COMPOSE_FILE" "$@"
}

# ── Bring the stack up ──────────────────────────────────────────────────────
log "Starting the stack (project: ${PROJECT}, port: ${HTTP_PORT})"
compose up -d

APP="$(compose ps -q paymenter)"
[[ -n "$APP" ]] || die "the paymenter container did not start — see: docker compose -p ${PROJECT} -f ${COMPOSE_FILE} logs"

# First boot runs migrations and seeding, which takes appreciably longer than the container
# takes to exist. Wait for the app to actually answer rather than sleeping a fixed guess.
# `migrate:status` starts answering while seeding is still running, and a settings change
# made in that window is lost. Wait for the settings table to actually hold rows, which is
# the last thing first boot does and the thing the next step depends on.
# `migrate:status` starts answering while seeding is still running, and anything done in
# that window is lost. Wait for the settings table to actually hold rows — the last thing
# first boot does, and what every step below depends on.
#
# The count is wrapped in markers because tinker also prints connection warnings while the
# database is still starting, and those contain digits (the port, 3306) that a naive digit
# filter happily mistakes for the answer.
# Waiting for "any settings row" is not enough — seeding writes them one at a time, and the
# very next step overwrites `app_url` specifically. Wait for that exact row to exist, or the
# seeder writes its default on top of the value set here and login breaks.
app_url_seeded() {
  docker exec "$APP" php artisan tinker --execute \
    'echo "<<" . (DB::table("settings")->where("key","app_url")->exists() ? 1 : 0) . ">>";' 2>/dev/null \
    | grep -oE '<<[01]>>' | head -1 | tr -dc '01' || true
}

log "Waiting for the application to answer (first boot runs migrations and seeds)…"
READY=0
for _ in $(seq 1 72); do
  if [[ "$(app_url_seeded)" == "1" ]]; then READY=1; break; fi
  sleep 5
done
(( READY )) || die "the application did not finish first boot in six minutes — check: docker logs ${APP}"

# Seeding writes app_url early in a longer run; give the rest of it a moment to land so the
# canonical URL is not overwritten a second later.
sleep 10
ok "application is up and seeded"

# Paymenter takes its canonical URL from the `app_url` *database setting*, not from the
# APP_URL environment variable. A fresh install seeds it as http://localhost, and while the
# two disagree every request redirects to localhost — the site loads but nobody can log in.
# This is the single most important step here; setting APP_URL alone is not enough.
docker exec "$APP" php artisan app:settings:change app_url "$PUBLIC_URL" >/dev/null 2>&1 || true
docker exec "$APP" php artisan config:clear >/dev/null 2>&1 || true

# Read it back rather than trusting the command's exit status — this is the one setting that
# silently breaks login, so it is worth confirming. `|| true` keeps a no-match from aborting
# the install under `set -e`.
CANONICAL="$(docker exec "$APP" php artisan tinker --execute \
  'echo "<<" . config("settings.app_url") . ">>";' 2>/dev/null \
  | grep -oE '<<https?://[^>]+>>' | head -1 | sed 's/^<<//; s/>>$//' || true)"

if [[ "$CANONICAL" == "$PUBLIC_URL" ]]; then
  ok "canonical URL set to ${CANONICAL}"
else
  warn "canonical URL is '${CANONICAL:-unset}', expected '${PUBLIC_URL}'."
  warn "Fix with: docker exec ${APP} php artisan app:settings:change app_url '${PUBLIC_URL}'"
fi

# Generated URLs come from the cached config, so this must run after the URL is known.
docker exec "$APP" php artisan config:cache >/dev/null 2>&1 || warn "config:cache failed — links may use the wrong host"
ok "configuration cached against ${PUBLIC_URL}"

# ── Admin account ───────────────────────────────────────────────────────────
HAS_ADMIN="$(docker exec "$APP" php artisan tinker --execute \
  'echo "<<" . App\Models\User::where("role_id",1)->count() . ">>";' 2>/dev/null \
  | grep -oE '<<[0-9]+>>' | head -1 | tr -dc '0-9' || true)"

if [[ "${HAS_ADMIN:-0}" -gt 0 ]]; then
  ok "an admin account already exists — leaving it alone"
  ADMIN_NOTE="existing admin unchanged"
else
  docker exec "$APP" php artisan app:user:create Admin User "$ADMIN_EMAIL" "$ADMIN_PASSWORD" 1 >/dev/null 2>&1 || true

  # Confirm the account is really there; app:user:create reports success in cases where
  # the row is not written, and an install that ends with no way in is worthless.
  CREATED="$(docker exec "$APP" php artisan tinker --execute \
    'echo "<<" . App\Models\User::where("role_id",1)->count() . ">>";' 2>/dev/null \
    | grep -oE '<<[0-9]+>>' | head -1 | tr -dc '0-9' || true)"

  if [[ "${CREATED:-0}" -gt 0 ]]; then
    ok "admin created: ${ADMIN_EMAIL}"
    ADMIN_NOTE="${ADMIN_EMAIL} / ${ADMIN_PASSWORD}"
  else
    warn "the admin account was not created. Create it with:"
    warn "  docker exec ${APP} php artisan app:user:create Admin User '${ADMIN_EMAIL}' '<password>' 1"
    ADMIN_NOTE="NOT CREATED — see the warning above"
  fi
fi

# Log files created by root-run artisan commands are unreadable by the web user otherwise.
docker exec "$APP" sh -c 'chown -R nginx:nginx /app/storage 2>/dev/null || true'

# ── Done ────────────────────────────────────────────────────────────────────
echo
log "Installed."
echo "  URL:        ${PUBLIC_URL}"
echo "  Admin:      ${ADMIN_NOTE}"
echo "  Settings:   ${ENV_FILE}"
echo
echo "  Logs:       docker compose -p ${PROJECT} -f ${COMPOSE_FILE} logs -f"
echo "  Stop:       docker compose -p ${PROJECT} -f ${COMPOSE_FILE} down"
echo "  Update:     git pull && docker compose -p ${PROJECT} -f ${COMPOSE_FILE} up -d"
echo
(( GENERATED_ADMIN_PASSWORD )) && [[ "$ADMIN_NOTE" != "existing admin unchanged" ]] \
  && warn "The admin password above was generated and is not stored anywhere. Save it now."
exit 0
