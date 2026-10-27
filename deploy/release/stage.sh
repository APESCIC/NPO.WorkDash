#!/bin/bash
#
# Stage a runnable copy of the app from a git ref: committed code, production
# Composer dependencies and built Vite assets. Shared by the Cloudron archive
# (deploy/cloudron/build-release.sh) and the release zip (build-package.sh).
#
# Usage: deploy/release/stage.sh <ref> <empty target directory>
#
# Requires git, PHP 8.3+, Composer 2 and Node 22+ (Git Bash works on Windows).

set -euo pipefail

ref="${1:?Usage: stage.sh <ref> <target>}"
target="${2:?Usage: stage.sh <ref> <target>}"
root="$(git rev-parse --show-toplevel)"
# ^{commit}: an annotated tag's own object ID would not match the deployed commit.
sha="$(git -C "$root" rev-parse --short "$ref^{commit}")"

mkdir -p "$target"
git -C "$root" archive --format=tar "$ref" | tar -x -C "$target"
echo "$sha" > "$target/REVISION"

cd "$target"

# Git Bash on Windows cannot run the composer.bat wrapper, so fall back to the phar.
composer() {
    if type -P composer > /dev/null; then
        command composer "$@"
    else
        php "$(type -P composer.phar)" "$@"
    fi
}

# --no-scripts: package discovery runs on the server during deploy or install.
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress --no-scripts

# The Wayfinder Vite plugin calls artisan, which needs an .env to boot.
cp .env.example .env
php artisan key:generate --no-interaction > /dev/null
npm ci --no-audit --no-fund
npm run build

rm -rf node_modules .env bootstrap/cache/*.php public/hot
