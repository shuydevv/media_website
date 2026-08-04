#!/usr/bin/env bash
#
# Manually roll back to the release before the current one.
# Stateless: picks the second-most-recent directory under releases/.
# Does NOT revert database migrations — check those by hand if the
# release you're rolling back from ran new ones.
#
set -euo pipefail

APP="${APP:-/var/www/poltav}"
RELEASES="$APP/releases"
CUR="$APP/current"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.2-fpm}"

log() { echo "[rollback] $*"; }

CURRENT_TARGET="$(readlink -f "$CUR")"
TARGET="$(ls -1dt "$RELEASES"/*/ 2>/dev/null | sed 's:/$::' | grep -vFx "$CURRENT_TARGET" | head -n1 || true)"

if [ -z "$TARGET" ]; then
  log "❌ No previous release found under $RELEASES to roll back to."
  exit 1
fi

log "Current: $CURRENT_TARGET"
log "Rolling back to: $TARGET"

php "$CUR/artisan" down --render="errors::503" || true

ln -sfn "$TARGET" "$CUR"
cd "$CUR"

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan up

if command -v sudo >/dev/null && sudo -n systemctl reload "$PHP_FPM_SERVICE" 2>/dev/null; then
  log "Reloaded $PHP_FPM_SERVICE"
else
  log "⚠️  Could not reload $PHP_FPM_SERVICE — check sudoers."
fi

log "✅ Rolled back to $TARGET"
