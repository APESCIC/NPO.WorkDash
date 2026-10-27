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
# ^{commit}: an annotated tag's own object ID would not match the deployed commit.
sha="$(git -C "$root" rev-parse --short "$ref^{commit}")"
version="$(git -C "$root" show "$ref:VERSION" | tr -d '[:space:]')"
name="npo-workdash-${version}-${sha}"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

echo "Building $name from $ref"

"$root/deploy/release/stage.sh" "$ref" "$work"

mkdir -p "$root/dist"
# A relative output path, because GNU tar reads "C:/..." (Git Bash) as a remote host.
cd "$root/dist"
# List top-level entries explicitly so member names have no "./" prefix.
# shellcheck disable=SC2046
tar -czf "$name.tar.gz" -C "$work" $(cd "$work" && ls -A)

echo "Created dist/$name.tar.gz"
