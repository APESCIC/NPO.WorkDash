#!/bin/bash
#
# Build a release from a git tag and deploy it to the Cloudron LAMP app with
# the cloudron CLI, then verify it is live.
#
# Usage: deploy/cloudron/cloudron-deploy.sh <tag> [--bootstrap]
#
#   <tag>         a release tag pushed to origin, for example v0.1.1
#   --bootstrap   first deploy to a fresh LAMP app: pin PHP 8.3 and create
#                 /app/data/shared/.env with a new APP_KEY (never overwritten)
#
# Environment:
#   CLOUDRON_APP  app ID or location (default: production)
#   DEPLOY_URL    public URL to verify (default: production)
#
# Requires a logged-in cloudron CLI (`cloudron login <server>`) plus everything
# build-release.sh needs. Run from Git Bash on Windows.

set -euo pipefail

APP="${CLOUDRON_APP:-b253bb6f-f66a-49cc-aa7f-79464eebdc91}"
URL="${DEPLOY_URL:-https://npo-workdash.apes.org.uk}"
URL="${URL%/}"
PHP_VERSION=8.3

tag=""
bootstrap=0
for arg in "$@"; do
    case "$arg" in
        --bootstrap) bootstrap=1 ;;
        -h | --help) sed -n '3,17p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        -*) echo "Unknown option: $arg" >&2; exit 2 ;;
        *) tag="$arg" ;;
    esac
done
[[ -n "$tag" ]] || { echo "Usage: $0 <tag> [--bootstrap]" >&2; exit 2; }

log() {
    echo "==> $*"
}

fail() {
    echo "ERROR: $*" >&2
    echo "Live code is unchanged unless deploy.sh reached the symlink switch. To go back: cloudron exec --app $APP -- bash /app/data/rollback.sh, then cloudron restart --app $APP (see docs/deploy/cloudron.md)." >&2
    exit 1
}

remote() {
    cloudron exec --app "$APP" -- bash -c "$1"
}

root="$(git rev-parse --show-toplevel)"
cd "$root"

git rev-parse --verify --quiet "refs/tags/$tag" > /dev/null || { echo "Tag $tag does not exist locally (git fetch --tags)." >&2; exit 1; }
git ls-remote --exit-code --tags origin "refs/tags/$tag" > /dev/null || { echo "Tag $tag is not on origin. Push it first: git push origin $tag" >&2; exit 1; }
cloudron status --app "$APP" > /dev/null || { echo "cloudron CLI cannot reach app $APP. Run: cloudron login <server>" >&2; exit 1; }

sha="$(git rev-parse --short "$tag^{commit}")"
version="$(git show "$tag:VERSION" | tr -d '[:space:]')"
name="npo-workdash-${version}-${sha}"
archive="dist/$name.tar.gz"
remote_archive="/app/data/releases/$name.tar.gz"

log "Deploying $tag ($sha) to $APP ($URL)"

log "Building $archive"
bash deploy/cloudron/build-release.sh "$tag"
[[ -f "$archive" ]] || fail "build did not produce $archive"

log "Uploading release"
remote "mkdir -p /app/data/releases"
cloudron push --app "$APP" "$archive" /app/data/releases/

log "Installing scripts and Apache config from the release"
remote "set -euo pipefail
tmp=\$(mktemp -d)
tar -xzf '$remote_archive' -C \"\$tmp\" deploy/cloudron
cp \"\$tmp\"/deploy/cloudron/{run.sh,deploy.sh,rollback.sh} /app/data/
chmod +x /app/data/{run.sh,deploy.sh,rollback.sh}
cp \"\$tmp\"/deploy/cloudron/apache-app.conf /app/data/apache/app.conf
rm -rf \"\$tmp\""

if [[ "$bootstrap" -eq 1 ]]; then
    log "Bootstrap: pinning PHP $PHP_VERSION"
    remote "set -euo pipefail
if grep -q '^PHP_VERSION=' /app/data/PHP_VERSION 2> /dev/null; then
    sed -i 's/^PHP_VERSION=.*/PHP_VERSION=$PHP_VERSION/' /app/data/PHP_VERSION
else
    echo 'PHP_VERSION=$PHP_VERSION' >> /app/data/PHP_VERSION
fi"

    log "Bootstrap: creating /app/data/shared/.env if missing"
    remote "set -euo pipefail
env=/app/data/shared/.env
if [[ -f \"\$env\" ]]; then
    echo 'shared/.env already exists, leaving it unchanged'
else
    mkdir -p /app/data/shared
    tar -xzf '$remote_archive' -O deploy/cloudron/env.example > \"\$env\"
    key=\"base64:\$(php$PHP_VERSION -r 'echo base64_encode(random_bytes(32));')\"
    sed -i \"s|^APP_ENV=.*|APP_ENV=production|; s|^APP_KEY=.*|APP_KEY=\$key|\" \"\$env\"
    chown www-data:www-data \"\$env\"
    chmod 640 \"\$env\"
    echo 'Created shared/.env with a new APP_KEY. Copy it to the password manager from the Cloudron File Manager.'
fi"
fi

remote "[[ -f /app/data/shared/.env ]]" || fail "/app/data/shared/.env is missing. Re-run with --bootstrap for a fresh app."

log "Running deploy.sh on the app"
remote "bash /app/data/deploy.sh '$remote_archive'" || fail "deploy.sh failed (see output above). The site may still be in maintenance mode."

log "Restarting the app"
cloudron restart --app "$APP"

log "Waiting for $URL/up"
status=""
for _ in $(seq 1 30); do
    status="$(curl -s -o /dev/null -w '%{http_code}' "$URL/up" || true)"
    [[ "$status" == "200" ]] && break
    sleep 5
done
[[ "$status" == "200" ]] || fail "$URL/up returned ${status:-no response} after 150 seconds"

live="$(remote "cat /app/data/workdash/REVISION" | tr -d '[:space:]')"
[[ "$live" == "$sha" ]] || fail "live REVISION is '$live', expected $sha"

log "Deployed $tag ($sha): $URL/up returned 200 and REVISION matches"
