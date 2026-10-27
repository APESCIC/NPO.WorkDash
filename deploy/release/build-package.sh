#!/bin/bash
#
# Build the all-in-one release zip for shared hosting, Cloudron and VPS installs.
#
# Usage: deploy/release/build-package.sh <tag>      (for example v0.2.0)
# Output: dist/npo-workdash-<version>.zip and dist/manifest.json
#
# The zip holds one top-level npo-workdash/ folder with vendor/ and public/build
# included, so hosts need neither Composer nor Node. Runs in the release
# workflow; needs the repository's own Composer dependencies installed and an
# .env, because the manifest is written with this checkout's artisan.

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
app="$work/npo-workdash"

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

mkdir -p "$root/dist"
rm -f "$root/dist/npo-workdash-$version.zip"
(cd "$work" && zip -qrX "$root/dist/npo-workdash-$version.zip" npo-workdash)
cp "$app/manifest.json" "$root/dist/manifest.json"

echo "Created dist/npo-workdash-$version.zip"
