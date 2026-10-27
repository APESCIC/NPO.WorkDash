#!/bin/bash
#
# Build the all-in-one release zip for shared hosting, Cloudron and VPS installs.
#
# Usage: deploy/release/build-package.sh <tag>      (for example v0.2.0)
# Output: dist/npo-workdash-<version>.zip and dist/manifest.json
#
# The zip holds the release layout, with vendor/ and public/build included so
# hosts need neither Composer nor Node:
#
#   npo-workdash/current.php            returns the live release path
#   npo-workdash/artisan                runs the live release's artisan (cron)
#   npo-workdash/public/                web root (or copy its contents into public_html)
#   npo-workdash/releases/<version>/    the app
#   npo-workdash/shared/storage/        storage shared by all releases (.env is created here)
#
# Runs in the release workflow; needs the repository's own Composer
# dependencies installed and an .env, because the manifest is written with
# this checkout's artisan.

set -euo pipefail

tag="${1:?Usage: build-package.sh <tag>}"
root="$(git rev-parse --show-toplevel)"
version="$(git -C "$root" show "$tag:VERSION" | tr -d '[:space:]')"

if [[ "v$version" != "$tag" ]]; then
    echo "VERSION ($version) does not match the tag ($tag)." >&2
    exit 1
fi

work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
layout="$work/npo-workdash"
app="$layout/releases/$version"

"$root/deploy/release/stage.sh" "$tag" "$app"

# Development tooling and sources that are not needed at runtime.
(
    cd "$app"
    rm -rf tests .github .cursor deploy resources/js resources/css \
        package.json package-lock.json vite.config.ts tsconfig.json eslint.config.js \
        components.json phpunit.xml phpstan.neon pint.json .editorconfig .gitattributes \
        .gitleaks.toml .npmrc .prettierrc .prettierignore AGENTS.md
)

(cd "$root" && php artisan release:manifest "$app" --release-version="$version")

starter="$root/deploy/release/starter"

# Web root: the app's public assets plus the starter entry point and server check.
mkdir -p "$layout/public"
cp -R "$app/public/." "$layout/public/"
cp "$starter/public/index.php" "$starter/public/workdash-path.php" "$starter/public/.htaccess" \
    "$starter/public/check.php" "$layout/public/"
cp "$app/app/Install/RequirementsChecker.php" "$layout/public/"

cp "$starter/artisan" "$layout/artisan"
chmod +x "$layout/artisan"
printf "<?php\n\n// The live release. Updates rewrite only this file.\nreturn __DIR__.'/releases/%s';\n" "$version" > "$layout/current.php"

mkdir -p "$layout"/shared/storage/{app/public,framework/{cache/data,sessions,views},logs}
find "$layout/shared" -type d -empty -exec touch {}/.gitkeep \;

mkdir -p "$root/dist"
rm -f "$root/dist/npo-workdash-$version.zip"
(cd "$work" && zip -qrX "$root/dist/npo-workdash-$version.zip" npo-workdash)
cp "$app/manifest.json" "$root/dist/manifest.json"

echo "Created dist/npo-workdash-$version.zip"
