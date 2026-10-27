#!/bin/bash
#
# Install a release zip, then update it to a newer signed zip served from a
# local update server, on a bare Apache + PHP container. Used by the
# Compatibility workflow.
#
# Usage (as root, in a php:*-apache container with pdo_mysql and zip):
#   deploy/release/compat-test.sh <artifact folder> [--restricted]
#
# The artifact folder holds install.zip, server/ (feed and newer zip),
# public-key.txt and new-version.txt. --restricted applies shared-hosting PHP
# limits and drives the update one step per PHP process, as the web updater
# does. Database: DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD.

set -euo pipefail

ci="$(realpath "${1:?Usage: compat-test.sh <artifact folder> [--restricted]}")"
restricted="${2:-}"
home=/srv/home
here="$(cd "$(dirname "$0")" && pwd)"

fail() {
    echo "::error::$*"
    tail -n 80 "$home"/npo-workdash/shared/storage/logs/*.log "$home"/npo-workdash/shared/storage/logs/updates/*.log 2> /dev/null || true
    exit 1
}

if [[ "$restricted" == --restricted ]]; then
    echo "==> Applying shared-hosting PHP limits"
    cat > /usr/local/etc/php/conf.d/zz-restricted.ini << 'EOF'
max_execution_time=30
memory_limit=256M
disable_functions=exec,proc_open,shell_exec,system,passthru,popen,symlink
EOF
    php -r 'exit(function_exists("exec") || function_exists("proc_open") || function_exists("symlink") ? 1 : 0);' \
        || fail "The restricted PHP settings were not applied"
fi

php -v | head -n 1

bash "$here/smoke-test.sh" "$ci/install.zip" "$home"

new="$(tr -d '[:space:]' < "$ci/new-version.txt")"
public_key="$(tr -d '[:space:]' < "$ci/public-key.txt")"

echo "==> Pointing the updater at a local update server with $new"
cat >> "$home/npo-workdash/shared/.env" << EOF
UPDATES_FEED_URL=http://127.0.0.1:9000/feed/v1
UPDATES_PUBLIC_KEY=$public_key
EOF
php -S 127.0.0.1:9000 -t "$ci/server" > /tmp/update-server.log 2>&1 &
sleep 1

artisan() {
    su -s /bin/bash www-data -c "php $home/npo-workdash/artisan $*"
}

artisan app:update --check || fail "app:update --check failed"

if [[ "$restricted" == --restricted ]]; then
    echo "==> Updating one step per PHP process (30-second limit each)"
    for attempt in $(seq 1 300); do
        output="$(artisan app:update --step)" || fail "Update step failed: $output"
        echo "$output"
        [[ "$output" == *status=succeeded* ]] && break
    done
    [[ "$output" == *status=succeeded* ]] || fail "The update did not finish"
else
    echo "==> Updating with app:update"
    artisan app:update --no-interaction || fail "app:update failed"
fi

grep -q "releases/$new'" "$home/npo-workdash/current.php" || fail "current.php does not point at releases/$new"

health="$(curl -s http://127.0.0.1/up)"
echo "$health"
[[ "$health" == *"\"status\":\"ok\""* && "$health" == *"\"version\":\"$new\""* ]] || fail "/up is not healthy on $new"
[[ "$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1/login)" == 200 ]] || fail "/login failed after the update"
[[ "$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1/install)" == 404 ]] || fail "/install reopened after the update"

echo "Installed and updated to $new."
