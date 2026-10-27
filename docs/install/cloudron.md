# Install on Cloudron

NPO.WorkDash runs on Cloudron's **LAMP** app. The full operator guide, covering one-time setup, the release scripts, rollback and troubleshooting, is [docs/deploy/cloudron.md](../deploy/cloudron.md). This page is the short version.

On Cloudron the browser wizard is switched off, because the app is reachable as soon as it is deployed. Install from the app's **Web Terminal** instead.

## 1. Upload

1. Install the **LAMP** app from the App Store and set its memory limit to 1 GB.
2. Pin PHP 8.3 in `/app/data/PHP_VERSION`.
3. Deploy a release with `deploy/cloudron/cloudron-deploy.sh vX.Y.Z --bootstrap` from a machine with the Cloudron CLI (see the operator guide). This uploads the release, `run.sh`, the Apache config and the environment template.

## 2. Database

Nothing to do: the app reads the MySQL addon's `CLOUDRON_MYSQL_*` variables.

## 3. Install

In the Web Terminal:

```bash
gosu www-data php8.3 /app/data/workdash/artisan app:install
gosu www-data php8.3 /app/data/workdash/artisan config:cache
```

It asks for the Super Admin and the first workspace. For a scripted install, pass `--no-interaction` with `--admin-name`, `--admin-email`, `--admin-password` and `--workspace`. Then restart the app from the dashboard.

> Screenshot: Web Terminal after app:install.

## 4. Cron and the queue

`run.sh` keeps the scheduler and a queue worker running, so no cron job is needed. Cloudron sets `CLOUDRON=1`, which makes `QUEUE_RUNNER` default to `worker`, so jobs are never processed twice.

## 5. Your first update

Either use **Updates** in the app, or the Web Terminal:

```bash
gosu www-data php8.3 /app/data/workdash/artisan app:update --check
gosu www-data php8.3 /app/data/workdash/artisan app:update
```

Restart the app afterwards. See [updating.md](updating.md).
