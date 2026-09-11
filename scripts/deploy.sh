#!/usr/bin/env bash
#
# Deploy the current main branch to a server and rebuild Laravel's compiled caches.
#
# The caches are the point of this script. Without them Laravel re-parses every config
# file, re-registers every route and re-scans every event listener on each request, which
# on this deployment costs about 15% of server-side page time. With them, a deploy that
# forgets to rebuild serves the *previous* commit's configuration and routes — a failure
# that looks like "my change did nothing" rather than like an error. So pulling and
# rebuilding belong in one command, and this is it.
#
#   ssh <server> 'bash -s' < scripts/deploy.sh
#   scripts/deploy.sh              # run on the server itself
#
# Views are cleared and then rebuilt. Clearing alone leaves the first visitor to each
# screen compiling its Blade, which measured 2.2x slower than a warm hit on the
# catalogue — noticeable on a day with several deploys.

set -euo pipefail

ROOT="${PAYMENTER_ROOT:-/opt/paymentor-proxy-admin-panel}"
COMPOSE_FILE="${PAYMENTER_COMPOSE:-docker-compose.vps.yml}"

cd "$ROOT"

# `< /dev/null` is not decoration. Piped in over ssh (`ssh host 'bash -s' < deploy.sh`)
# the script *is* stdin, and `exec -T` inherits it — so the first artisan call swallowed
# the rest of the script and the deploy stopped after config:clear, silently, exit 0.
run() { docker compose -f "$COMPOSE_FILE" exec -T paymenter "$@" < /dev/null; }

echo "==> Pulling"
# --ff-only so a diverged server tree fails loudly here rather than producing a merge
# commit nobody asked for. Divergence means someone edited files on the server; that is a
# thing to look at, not to paper over.
git pull --ff-only

echo "==> Clearing compiled caches"
# Cleared before rebuilding, so that a rebuild which fails half way leaves the site
# running uncached and correct rather than cached and stale.
run php artisan config:clear
run php artisan route:clear
run php artisan event:clear
run php artisan view:clear

echo "==> Rebuilding compiled caches"
run php artisan config:cache
run php artisan route:cache
run php artisan event:cache
run php artisan view:cache

echo "==> Applying any new migrations"
run php artisan migrate --force

echo "==> Deployed: $(git log --oneline -1)"
