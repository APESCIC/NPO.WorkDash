#!/bin/bash
#
# Deploy a release archive on the Cloudron LAMP app.
#
# Usage (as root in the app's Web Terminal):
#   bash /app/data/deploy.sh /app/data/releases/npo-workdash-<version>-<sha>.tar.gz
#
# Layout:
#   /app/data/releases/<release>/   extracted releases (the last 5 are kept)
#   /app/data/shared/.env           environment, shared by all releases
#   /app/data/shared/storage/       storage, shared by all releases
#   /app/data/workdash              symlink to the live release (Apache docroot is its public/)

set -euo pipefail

DATA=/app/data
RELEASES="$DATA/releases"
SHARED="$DATA/shared"
CURRENT="$DATA/workdash"
KEEP_RELEASES=5

archive="${1:?Usage: deploy.sh <release archive .tar.gz>}"
[[ -f "$archive" ]] || { echo "Archive not found: $archive" >&2; exit 1; }

if [[ -e "$CURRENT" && ! -L "$CURRENT" ]]; then
    echo "$CURRENT exists and is not a symlink. Move it aside before deploying." >&2
    exit 1
fi

if [[ ! -f "$SHARED/.env" ]]; then
    echo "Create $SHARED/.env first (template: deploy/cloudron/env.example)." >&2
    exit 1
fi

# The file holds `; comment` lines and `PHP_VERSION=8.3`.
php_version="$(grep -v '^[[:space:]]*;' "$DATA/PHP_VERSION" 2> /dev/null | grep -oE '[0-9]+\.[0-9]+' | tail -n1 || true)"
if [[ -n "$php_version" ]] && command -v "php$php_version" > /dev/null; then
    PHP_BIN="php$php_version"
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

release="$(basename "$archive" .tar.gz)-$(date -u +%Y%m%d%H%M%S)"
target="$RELEASES/$release"

echo "==> Extracting $archive to $target"
mkdir -p "$target"
tar -xzf "$archive" -C "$target"

echo "==> Linking shared .env and storage"
mkdir -p "$SHARED"/storage/{app/public,framework/{cache/data,sessions,views},logs}
rm -rf "$target/storage"
ln -s "$SHARED/storage" "$target/storage"
ln -sfn "$SHARED/.env" "$target/.env"
chown -R www-data:www-data "$target" "$SHARED"

artisan() {
    as_www_data "$PHP_BIN" "$target/artisan" "$@" --no-interaction
}

if [[ -L "$CURRENT" ]]; then
    echo "==> Entering maintenance mode"
    as_www_data "$PHP_BIN" "$CURRENT/artisan" down --retry=15 --no-interaction || true
fi

echo "==> Preparing release with $PHP_BIN"
artisan package:discover
artisan storage:link --force
artisan migrate --force
artisan optimize

echo "==> Switching $CURRENT to $release"
ln -sfn "$target" "$CURRENT.next"
mv -T "$CURRENT.next" "$CURRENT"

echo "==> Restarting queue workers and leaving maintenance mode"
as_www_data "$PHP_BIN" "$CURRENT/artisan" queue:restart --no-interaction
as_www_data "$PHP_BIN" "$CURRENT/artisan" up --no-interaction

echo "==> Pruning old releases (keeping $KEEP_RELEASES)"
ls -1dt "$RELEASES"/*/ | tail -n +$((KEEP_RELEASES + 1)) | xargs -r rm -rf

echo
echo "Deployed $release."
echo "Restart the app from the Cloudron dashboard so PHP's opcache and run.sh pick up the new release."
