#!/bin/bash
#
# Cloudron LAMP startup script for NPO.WorkDash.
#
# Copy to /app/data/run.sh. Cloudron runs it on every container start, before
# Apache starts, so it must not block: long-running processes are started in
# the background and restarted automatically if they exit or are killed.
#
# Output goes to the app's log (Cloudron dashboard, Logs).

set -u

APP_DIR="${APP_DIR:-/app/data/workdash}"
PHP_VERSION_FILE=/app/data/PHP_VERSION

# The CLI `php` is always the image default, so use the version the web server runs.
if [[ -f "$PHP_VERSION_FILE" ]] && command -v "php$(tr -d '[:space:]' < "$PHP_VERSION_FILE")" > /dev/null; then
    PHP_BIN="php$(tr -d '[:space:]' < "$PHP_VERSION_FILE")"
else
    PHP_BIN=php
fi

# Run as www-data while keeping the environment, so CLOUDRON_MYSQL_* and
# CLOUDRON_MAIL_* reach Laravel (plain `sudo -u` would strip them).
as_www_data() {
    if command -v gosu > /dev/null; then
        gosu www-data "$@"
    else
        sudo -E -u www-data "$@"
    fi
}

log() {
    echo "[run.sh $(date -u +%H:%M:%S)] $*"
}

if [[ ! -f "$APP_DIR/artisan" ]]; then
    log "No app found at $APP_DIR, skipping queue worker and scheduler. Deploy first (docs/deploy/cloudron.md)."
    exit 0
fi

# Cloudron can rotate addon credentials between restarts, so rebuild the
# config cache from the current environment on every start.
log "Caching configuration with $PHP_BIN"
as_www_data "$PHP_BIN" "$APP_DIR/artisan" config:cache --no-interaction || log "config:cache failed"

# Restart a command forever: on normal exit (for example --max-time or
# queue:restart after a deploy), on crash, or when killed.
supervise() {
    local name="$1"
    shift

    (
        while true; do
            log "Starting $name"
            as_www_data "$PHP_BIN" "$APP_DIR/artisan" "$@"
            log "$name exited with status $?, restarting in 5 seconds"
            sleep 5
        done
    ) &
}

supervise "queue worker" queue:work --sleep=3 --tries=3 --max-time=3600 --no-interaction
supervise "scheduler" schedule:work --no-interaction

log "Queue worker and scheduler started"
