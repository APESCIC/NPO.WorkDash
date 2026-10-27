# NPO-WorkDash

[![CI](https://github.com/APESCIC/NPO.WorkDash/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/APESCIC/NPO.WorkDash/actions/workflows/ci.yml)

Hosted multi-tenant SaaS for UK charities and CICs, built with Laravel 12, React (Inertia, TypeScript, Tailwind), MySQL 8, Pest and Vite.

Production runs on the Cloudron LAMP app with MySQL 8.0. Local development uses [Laragon](https://laragon.org/) on Windows.

Key technical decisions are recorded as [architecture decision records](docs/adr/README.md). Deployment to production (<https://npo-workdash.apes.org.uk/>) is described in the [Cloudron deployment guide](docs/deploy/cloudron.md).

## Local setup

### Prerequisites

- **Laragon** with Apache, MySQL 8.x and Mailpit enabled (Laragon currently ships MySQL 8.4; production is MySQL 8.0, so avoid 8.4-only SQL).
- **PHP 8.3+ CLI** on your `PATH`, with `pdo_mysql`, `mbstring`, `intl`, `fileinfo`, `openssl` and `zip`. Composer is pinned to the PHP 8.3 platform so the lockfile works with Laragon's Apache PHP 8.3.
- **pcov** extension in the CLI PHP, only needed for `composer test:coverage`. On Windows, download the matching DLL (PHP version, thread-safe or not, x64) from `https://downloads.php.net/~windows/pecl/releases/pcov/`, copy `php_pcov.dll` into PHP's `ext` folder and add `extension=pcov` to `php.ini`.
- **Composer 2**
- **Node 22+** and npm

### 1. Serve the app from Laragon

Link the repository into Laragon's web root (PowerShell, adjust the source path):

```powershell
New-Item -ItemType Junction -Path C:\laragon\www\npo-workdash -Target C:\path\to\NPO.WorkDash
```

Create `C:\laragon\etc\apache2\sites-enabled\auto.npo-workdash.test.conf`:

```apache
<VirtualHost *:80>
    DocumentRoot "C:/laragon/www/npo-workdash/public"
    ServerName npo-workdash.test
    ServerAlias *.npo-workdash.test
    <Directory "C:/laragon/www/npo-workdash/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

In Laragon, click **Start All** (or **Reload** if it is already running). Laragon adds `npo-workdash.test` to the hosts file and starts Apache, MySQL and Mailpit.

### 2. Create the databases

Laragon's MySQL listens on `127.0.0.1:3306` as `root` with an empty password. Create the app and test databases with HeidiSQL or the mysql CLI:

```powershell
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -h 127.0.0.1 -uroot -e "CREATE DATABASE IF NOT EXISTS npo_workdash CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE IF NOT EXISTS npo_workdash_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 3. Install and configure

```powershell
composer install
npm install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
```

`.env.example` is already set up for Laragon: `APP_URL=http://npo-workdash.test`, MySQL on `127.0.0.1:3306` as `root`, and mail sent over SMTP to Mailpit on `127.0.0.1:1025`. It documents every environment variable the app reads. See [configuration and secrets](docs/security/secrets.md) for how staging, production and CI are configured, and how secrets and `APP_KEY` are handled.

Seeding in the `local` environment creates a fictional demo account: `demo@example.test` / `password`. Seeders never contain real personal data: seeded email addresses must use the reserved `example.test` domain, and a test enforces this. Outside `local`, `db:seed` creates no demo accounts. Each module adds its own demo data to `DemoSeeder` as it lands (the demo Super Admin arrives with the Super Admin area in M1).

Reset your local database to a clean, seeded state at any time with `php artisan migrate:fresh --seed`.

### 4. Run it

**Option A, Laragon (recommended):** run `npm run dev` for Vite with hot module replacement, then open <http://npo-workdash.test>. Laravel serves assets from the Vite dev server while `public/hot` exists.

**Option B, without Apache:** run `composer dev`. This starts `php artisan serve` on <http://127.0.0.1:8000>, the queue worker and Vite together. Set `APP_URL=http://127.0.0.1:8000` in `.env` if you use this option.

Emails (for example registration verification) appear in Mailpit at <http://127.0.0.1:8025>.

If a page renders blank and the Vite output shows `Failed to resolve import "@/..."` for a file that exists (this can happen after switching branches), stop and restart `npm run dev` or `composer dev`.

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
