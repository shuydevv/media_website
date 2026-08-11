#!/usr/bin/env bash
#
# Zero(ish)-downtime deploy for Laravel via atomic symlink switch.
# Run as the `deploy` user on the target server.
#
# Env overrides (all optional):
#   BRANCH=main REPO=... APP=/var/www/poltav RELEASES_TO_KEEP=5
#   HEALTHCHECK_URL=https://poltav.example.com/up PHP_FPM_SERVICE=php8.2-fpm
#
set -euo pipefail

APP="${APP:-/var/www/poltav}"
RELEASES="$APP/releases"
SHARED="$APP/shared"
CUR="$APP/current"
REPO="${REPO:-https://github.com/shuydevv/media_website.git}"
BRANCH="${BRANCH:-main}"
RELEASES_TO_KEEP="${RELEASES_TO_KEEP:-5}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.2-fpm}"
REL="$(date +%Y%m%d%H%M%S)"
NEW="$RELEASES/$REL"

LOCK_FILE="$APP/.deploy.lock"
mkdir -p "$RELEASES"
exec 200>"$LOCK_FILE"
if ! flock -n 200; then
  echo "❌ Another deploy is already running (lock: $LOCK_FILE)"
  exit 1
fi

log() { echo "[deploy] $*"; }

SWITCHED=0
PREV=""

# Safety net: if anything fails *after* we've flipped the symlink and taken
# the old release down, put the previous release back and bring the site up
# rather than leaving it stuck in maintenance mode.
on_error() {
  local exit_code=$?
  log "❌ Deploy failed (exit $exit_code)."
  if [ "$SWITCHED" = "1" ] && [ -n "$PREV" ]; then
    log "⏪ Rolling back symlink to previous release: $PREV"
    ln -sfn "$PREV" "$CUR"
    php "$CUR/artisan" up || true
    log "⚠️  Code was rolled back. If migrations ran before the failure, verify DB state manually — they were NOT auto-reverted."
  else
    # Nothing was switched yet; make sure whatever is live is not stuck down.
    php "$CUR/artisan" up || true
  fi
  exit "$exit_code"
}
trap on_error ERR

# ---------------------------------------------------------------------------
# 1) Fetch new release (nothing user-facing touched yet — safe to fail here)
# ---------------------------------------------------------------------------
git clone -b "$BRANCH" --depth=1 "$REPO" "$NEW"
cd "$NEW"

if [ -f "$SHARED/.env" ]; then
  cp "$SHARED/.env" .env
else
  cp "$CUR/.env" .env
fi
chmod 600 .env

rm -rf "$NEW/storage"
mkdir -p "$NEW/storage/framework/cache/data" "$NEW/storage/framework/sessions" "$NEW/storage/framework/testing" "$NEW/storage/framework/views"
# Only storage/app (uploads) and storage/logs are genuinely persistent —
# symlink those two into shared storage. storage/framework/{views,cache,
# sessions,testing} must stay LOCAL to this release, not shared: the
# pre-flight `view:clear`/`view:cache` etc. below run before cutover while
# $CUR still points at $PREV and is serving live traffic. If framework/
# were shared, `view:clear` would delete the compiled Blade views the live
# release is using out from under it, and concurrent PHP-FPM workers on
# $PREV racing to recompile the same shared, non-atomically-written cache
# file could interleave writes into a truncated/corrupted compiled view —
# surfacing later as a bogus "unexpected end of file, expecting elseif or
# else or endif" on some unrelated admin page until the next full
# view:cache. Keeping framework/* per-release makes the "still not live"
# comment above actually true.
ln -s "$SHARED/storage/app" "$NEW/storage/app"
ln -s "$SHARED/storage/logs" "$NEW/storage/logs"
mkdir -p "$NEW/bootstrap/cache"

composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

if [ -f package.json ]; then
  npm ci
  npm run build
fi

# ---------------------------------------------------------------------------
# 2) Pre-flight checks on the new release, still not live
# ---------------------------------------------------------------------------
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan migrate:status
php artisan migrate --pretend --force

# ---------------------------------------------------------------------------
# 3) Cutover — everything from here on is the short, must-succeed window
# ---------------------------------------------------------------------------
PREV="$(readlink -f "$CUR" || true)"

php "$CUR/artisan" down --render="errors::503" || true

ln -sfn "$NEW" "$CUR"
SWITCHED=1
cd "$CUR"

php artisan migrate --force --isolated
php artisan config:cache
php artisan route:cache
php artisan view:cache

[ -L public/storage ] || php artisan storage:link

php artisan queue:restart

php artisan up

if command -v sudo >/dev/null && sudo -n systemctl reload "$PHP_FPM_SERVICE" 2>/dev/null; then
  log "Reloaded $PHP_FPM_SERVICE"
else
  log "⚠️  Could not reload $PHP_FPM_SERVICE (opcache may serve stale code until it reloads on its own). Check sudoers."
fi

# ---------------------------------------------------------------------------
# 4) Health check — if the app doesn't answer, roll back automatically
# ---------------------------------------------------------------------------
HEALTHCHECK_URL="${HEALTHCHECK_URL:-$(grep -E '^APP_URL=' "$SHARED/.env" 2>/dev/null | cut -d= -f2-)/up}"
if [ -n "${HEALTHCHECK_URL:-}" ]; then
  log "Health-checking $HEALTHCHECK_URL"
  CODE="$(curl -fsS -o /dev/null -w '%{http_code}' --max-time 10 "$HEALTHCHECK_URL" || echo "000")"
  if [ "$CODE" != "200" ]; then
    log "❌ Health check returned HTTP $CODE"
    false # triggers on_error / rollback via trap
  fi
  log "✅ Health check OK ($CODE)"
else
  log "⚠️  No HEALTHCHECK_URL and no APP_URL in shared .env — skipping health check."
fi

trap - ERR

# ---------------------------------------------------------------------------
# 5) Housekeeping — prune old releases so disk doesn't fill up
# ---------------------------------------------------------------------------
ls -1dt "$RELEASES"/*/ 2>/dev/null | tail -n +$((RELEASES_TO_KEEP + 1)) | xargs -r rm -rf

log "✅ Deployed $REL successfully."
