# NPO-WorkDash

[![CI](https://github.com/APESCIC/NPO.WorkDash/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/APESCIC/NPO.WorkDash/actions/workflows/ci.yml)

Hosted multi-tenant SaaS for UK charities and CICs, built with Laravel 12, React (Inertia, TypeScript, Tailwind), MySQL 8, Pest and Vite.

Production runs on the Cloudron LAMP app with MySQL 8.0. Local development uses [Laragon](https://laragon.org/) on Windows.

AI agents (and humans) should start with [AGENTS.md](AGENTS.md): the work lifecycle from issue to deployed release, and the checks to run.

Key technical decisions are recorded as [architecture decision records](docs/adr/README.md). Deployment to production (<https://npo-workdash.apes.org.uk/>) is described in the [Cloudron deployment guide](docs/deploy/cloudron.md). To install a release on shared hosting (cPanel, Plesk, DirectAdmin), Cloudron or a VPS, and to update it, follow the [install and update guides](docs/install/README.md).

## Local development with Laragon

Laragon on Windows is the only supported local set-up. NPO WorkDash is its own Laragon project at <http://npo-workdash.test>: Apache running PHP 8.3 (as on the Cloudron app) through FastCGI, MySQL 8.4 with `npo_workdash_local` (tests use `npo_workdash_testing`), Vite hot reload on port 5177 and Mailpit for email. The values are in [docs/laragon/profile.md](docs/laragon/profile.md). Production runs MySQL 8.0, so avoid 8.4-only SQL.

### 1. Set up

Install Laragon (Full) with Apache, MySQL and Mailpit, start it, clone this repository, then run from the repository root:

```powershell
composer setup
```

`composer setup` runs [`scripts/laragon-setup.ps1`](scripts/laragon-setup.ps1). It is safe to run again and only touches this project:

- links `C:\laragon\www\npo-workdash` to the clone and checks PHP 8.3 and its extensions;
- checks WorkDash's own vhost runs PHP 8.3 through FastCGI. The first time, run `scripts\laragon-setup.ps1 -InstallVhost`, which copies [`docs/laragon/npo-workdash.test.conf`](docs/laragon/npo-workdash.test.conf) into Laragon; then restart Apache;
- copies `.env.example` (Laragon values: `APP_URL=http://npo-workdash.test`, MySQL as `root`, mail to Mailpit) to `.env`, and generates `APP_KEY`;
- installs Composer and npm dependencies, creates both databases and migrates;
- offers the demo data.

See [configuration and secrets](docs/security/secrets.md) for how staging, production and CI are configured.

### 2. Before any work: the live preview gate

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\preview.ps1
```

It loads the latest code (merging `origin/main`, never rebasing), installs changed dependencies, reports pending migrations, checks `/up` shows the checked-out revision through FastCGI, and starts Vite. Then open <http://npo-workdash.test>.

### 3. Run it

`composer dev` runs the queue worker and Vite with hot module replacement (or `npm run dev` for Vite only); Apache serves the app at <http://npo-workdash.test>. Emails (for example registration verification) appear in Mailpit at <http://127.0.0.1:8025>.

If a page renders blank and the Vite output shows `Failed to resolve import "@/..."` for a file that exists (this can happen after switching branches), stop and restart `npm run dev` or `composer dev`.

### 4. Demo data

`php artisan demo:seed` loads fictional demo data; `php artisan demo:reset` empties the local database and reloads it. Both refuse unless `APP_ENV=local`. Logins: [docs/laragon/demo-data.md](docs/laragon/demo-data.md).
### 5. Test

```powershell
composer test            # Pint style check, then the Pest suite
composer test:coverage   # Pest with a coverage summary (needs pcov)
composer analyse         # Larastan (PHPStan level 5)
composer ci:check        # everything CI runs, except the Vite build
```

Tests run against the `npo_workdash_testing` database (configured in `phpunit.xml`) and refresh it on every run.

Frontend checks:

```powershell
npm run types:check
npm run lint:check
npm run format:check
```

### Continuous integration

[`.github/workflows/ci.yml`](.github/workflows/ci.yml) runs on every push and pull request to `main`, in four parallel jobs:

- **Secret scanning**: gitleaks over the full git history, configured by [`.gitleaks.toml`](.gitleaks.toml).
- **PHP lint and static analysis**: Pint and Larastan.
- **Pest (MySQL 8.0)**: the full suite against a MySQL 8.0 service container, with a pcov coverage summary in the log.
- **Frontend**: Wayfinder route generation, ESLint, Prettier, `tsc` and `npm run build`.

### Notes

- `composer dev` does not start `php artisan pail`, because Pail needs the `pcntl` extension, which is not available on Windows. Use `storage/logs/laravel.log` instead.
