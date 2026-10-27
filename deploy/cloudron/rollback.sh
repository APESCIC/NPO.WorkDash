#!/bin/bash
#
# Point /app/data/workdash back at an earlier release.
#
# Usage (as root in the app's Web Terminal):
#   bash /app/data/rollback.sh                 roll back to the previous release
#   bash /app/data/rollback.sh <release dir>   roll back to a specific release
#
# This does not undo database migrations. If the release being rolled back
# added migrations the older code cannot work with, first run
#   php8.3 /app/data/workdash/artisan migrate:rollback --step=<n> --force
# from the newer release, and check docs/deploy/cloudron.md before doing so.

set -euo pipefail

DATA=/app/data
RELEASES="$DATA/releases"
CURRENT="$DATA/workdash"

[[ -L "$CURRENT" ]] || { echo "$CURRENT is not a symlink; nothing to roll back." >&2; exit 1; }

current_target="$(readlink -f "$CURRENT")"

if [[ $# -ge 1 ]]; then
    target="$(readlink -f "$1")"
else
    target="$(ls -1dt "$RELEASES"/*/ | xargs -n1 readlink -f | grep -vxF "$current_target" | head -n1 || true)"
fi

[[ -n "$target" && -f "$target/artisan" ]] || { echo "No release to roll back to." >&2; exit 1; }

if [[ -f "$DATA/PHP_VERSION" ]] && command -v "php$(tr -d '[:space:]' < "$DATA/PHP_VERSION")" > /dev/null; then
    PHP_BIN="php$(tr -d '[:space:]' < "$DATA/PHP_VERSION")"
else
    PHP_BIN=php
fi

as_www_data() {
    if command -v gosu > /dev/null; then
        gosu www-data "$@"
    else
        sudo -E -u www-data "$@"
    fi
}

echo "==> Rolling back from $(basename "$current_target") to $(basename "$target")"
as_www_data "$PHP_BIN" "$target/artisan" optimize --no-interaction
ln -sfn "$target" "$CURRENT.next"
mv -T "$CURRENT.next" "$CURRENT"
as_www_data "$PHP_BIN" "$CURRENT/artisan" queue:restart --no-interaction

echo "Rolled back. Restart the app from the Cloudron dashboard."
