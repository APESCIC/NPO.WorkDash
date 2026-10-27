# 0004. Hosting: Cloudron LAMP app

- Status: Accepted
- Date: 2026-10-27
- Deciders: Bambie Murphy

## Context

APES already runs a Cloudron server, which provides automatic TLS, backups, updates, a MySQL addon and email (sendmail/SMTP) addons. We want a hosting setup a small team can operate without Kubernetes or a dedicated DevOps role, while still supporting a queue worker and a scheduler.

## Decision

Host on the **Cloudron LAMP app** (Apache and PHP 8.3) with the **MySQL addon**, using two separate apps:

- **Staging** (for example `staging.workdash.example`): Stripe test mode, a non-production mail setup, seeded with fictional data only.
- **Production**: live keys, real customer data.

Application layout and processes:

- Code is deployed to `/app/data`, with `public/` as the Apache document root.
- Secrets live in `/app/data/.env`. Database credentials come from Cloudron's `CLOUDRON_MYSQL_*` environment variables, so no manual DB configuration is needed.
- The queue worker and scheduler run from `/app/data/run.sh`, which the LAMP app starts on boot and which restarts them if they exit.
- Deployment is manual for now, following [docs/deploy/cloudron.md](../deploy/cloudron.md).
- The server needs at least 8 GB RAM.

## Alternatives considered

- **Custom Cloudron package (own Dockerfile and manifest)**: cleaner and immutable, with zero-downtime upgrades, but more work to build and maintain. This is a good later upgrade path.
- **Laravel Forge or Vapor on a cloud provider**: polished, but adds another vendor, cost and operational surface outside the existing Cloudron estate.
- **Azure App Service or another PaaS**: viable, but duplicates backup and TLS work that Cloudron already does, and moves data away from the existing UK-hosted server.

## Consequences

- Backups, TLS and OS patching are handled by Cloudron. Restores must still be tested (milestone M8).
- Deploys are manual and briefly disruptive (maintenance mode while migrating). Zero-downtime deploys are out of scope.
- The app must read its configuration from Cloudron environment variables and must not assume a writable filesystem outside `/app/data`.
- A staging app doubles resource usage, but lets us rehearse migrations and the APES data cutover safely.
