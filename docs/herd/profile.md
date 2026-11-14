# Laravel Herd profile for NPO WorkDash

NPO WorkDash is its own Laravel Herd site. These are the values it needs. [`profile.json`](profile.json) holds the same values for `scripts/herd-setup.ps1` and `scripts/preview.ps1`, and a test keeps them in step with `.env.example`, `phpunit.xml` and `vite.config.ts`.

| Setting | Value |
| --- | --- |
| Herd site | `npo-workdash`, linked to your clone (`herd link`) |
| Hostname | `http://npo-workdash.test` |
| Document root | `public/` |
| Web server | Herd's nginx |
| PHP | 8.3, as on the Cloudron app, isolated for this site (`herd isolate 8.3`) while Herd's global PHP stays newer |
| PHP handler | FastCGI (`php-cgi`); `/up` reports `cgi-fcgi` |
| PHP extensions | bcmath, curl, fileinfo, intl, mbstring, openssl, pdo_mysql, zip |
| Database | Herd's MySQL 8.4 service, `npo_workdash_local` on `127.0.0.1:3306`, user `root`, no password; tests use `npo_workdash_testing`. Production is MySQL 8.0, so avoid 8.4-only SQL |
| Node | 22, from Herd's nvm |
| Vite | Port 5177, strict (`npm run dev`, or Herd's `vite` process; `composer dev` adds the queue worker) |
| Mail | Herd's mail catcher: SMTP `127.0.0.1:2525`, inbox in Herd > Mail. Nothing is sent |
| Env file | `.env.example` (already Herd values), copied to `.env` by the setup script |

## PHP version

Herd's global PHP is newer than Cloudron's, so the site is pinned to PHP 8.3 with `herd isolate 8.3 --site=npo-workdash`. `herd which-php npo-workdash` must print Herd's `php83` build. Use `herd php` and `herd composer` (or put `%USERPROFILE%\.config\herd\bin\php83` first on `PATH`) for Artisan, Composer and the tests, so the CLI matches the site.

## Cloudron's Apache

nginx ignores `public/.htaccess`, but Cloudron's Apache runs it through FastCGI too. Keep it free of `php_value` and `php_flag` outside `<IfModule php_module>` (FastCGI answers those with a 500 error), and keep the `HTTP_AUTHORIZATION` rewrite rule. The setup script and a test check both.

## The local block on `/up`

On the local preview only (`APP_ENV=local`), `/up` adds a `local` block with the PHP version, the PHP handler and the checked-out git revision. `scripts/preview.ps1` reads it. The scheduler check on `/up` fails locally unless `php artisan schedule:work` is running; the preview script reports that as a warning.
