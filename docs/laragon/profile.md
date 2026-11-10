# Laragon profile for NPO WorkDash

NPO WorkDash is its own Laragon project. These are the values it needs. [`profile.json`](profile.json) holds the same values for `scripts/laragon-setup.ps1` and `scripts/preview.ps1`, and a test keeps them in step with `.env.example`, `phpunit.xml` and `vite.config.ts`.

| Setting | Value |
| --- | --- |
| Project folder | `C:\laragon\www\npo-workdash` (a junction to your clone) |
| Hostname | `http://npo-workdash.test` |
| Document root | `public/` |
| Web server | Apache 2.4 |
| PHP | 8.3, as on the Cloudron app (`php-8.3.33-Win32-vs16-x64`) |
| PHP handler | FastCGI (mod_fcgid) in WorkDash's own vhost, [`npo-workdash.test.conf`](npo-workdash.test.conf), so Laragon's other sites keep their PHP |
| PHP extensions | bcmath, curl, fileinfo, intl, mbstring, openssl, pdo_mysql, zip |
| Database | MySQL 8.4, `npo_workdash_local` on `127.0.0.1:3306`, user `root`, no password; tests use `npo_workdash_testing`. Production is MySQL 8.0, so avoid 8.4-only SQL |
| Node | 22, with npm |
| Vite | Port 5177, strict (`npm run dev`; `composer dev` adds the queue worker) |
| Mail | Mailpit: SMTP `127.0.0.1:1025`, inbox <http://127.0.0.1:8025> |
| Env file | `.env.example` (already Laragon values), copied to `.env` by the setup script |

## Why its own vhost and FastCGI

Laragon runs one global PHP for Apache. With an NTS PHP selected, it runs PHP through FastCGI (`C:\laragon\etc\apache2\fcgid.conf`) with separate PHP processes per site, so one project's `.env` cannot leak into another. WorkDash's vhost adds its own `FcgidWrapper` for PHP 8.3, so it matches the Cloudron app while the other APES sites keep PHP 8.4.

A vhost file without the `auto.` prefix is left alone by Laragon ([Laragon: Pretty URLs](https://laragon.org/docs/pretty-urls)), so `scripts/laragon-setup.ps1 -InstallVhost` copies `npo-workdash.test.conf` into `sites-enabled` and removes `auto.npo-workdash.test.conf`. Apache must then restart. Checked on this PC: `/up` reports PHP 8.3.33 through `cgi-fcgi` for WorkDash while the other sites report PHP 8.4.

The setup script also checks that `public/.htaccess` has no `php_value` or `php_flag` outside `<IfModule php_module>` (FastCGI answers those with a 500 error) and still passes the `Authorization` header.

On the Laragon preview only (`APP_ENV=local`), `/up` adds a `local` block with the PHP version, the PHP handler and the checked-out git revision. `scripts/preview.ps1` reads it. The scheduler check on `/up` fails locally unless `php artisan schedule:work` is running; the preview script reports that as a warning.
