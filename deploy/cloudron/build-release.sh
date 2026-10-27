#!/bin/bash
#
# Build a deployable release archive for Cloudron from a git ref.
#
# Usage: deploy/cloudron/build-release.sh [ref]     (default: HEAD)
# Output: dist/npo-workdash-<version>-<short sha>.tar.gz
#
# The archive contains the committed code at <ref>, production Composer
# dependencies and the built Vite assets, so the server needs neither Node nor
# Composer. Requires git, PHP 8.3+, Composer 2 and Node 22+ (Git Bash works on
# Windows).

set -euo pipefail

ref="${1:-HEAD}"
root="$(git rev-parse --show-toplevel)"
sha="$(git -C "$root" rev-parse --short "$ref")"
version="$(git -C "$root" show "$ref:VERSION" | tr -d '[:space:]')"
name="npo-workdash-${version}-${sha}"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

echo "Building $name from $ref"

git -C "$root" archive --format=tar "$ref" | tar -x -C "$work"
echo "$sha" > "$work/REVISION"

cd "$work"

# Git Bash on Windows cannot run the composer.bat wrapper, so fall back to the phar.
composer() {
    if type -P composer > /dev/null; then
        command composer "$@"
    else
        php "$(type -P composer.phar)" "$@"
    fi
}

# --no-scripts: package discovery runs on the server during deploy.
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress --no-scripts

# The Wayfinder Vite plugin calls artisan, which needs an .env to boot.
cp .env.example .env
php artisan key:generate --no-interaction > /dev/null
npm ci --no-audit --no-fund
npm run build

rm -rf node_modules .env bootstrap/cache/*.php public/hot

mkdir -p "$root/dist"
# A relative output path, because GNU tar reads "C:/..." (Git Bash) as a remote host.
cd "$root/dist"
# List top-level entries explicitly so member names have no "./" prefix.
# shellcheck disable=SC2046
tar -czf "$name.tar.gz" -C "$work" $(cd "$work" && ls -A)

echo "Created dist/$name.tar.gz"
