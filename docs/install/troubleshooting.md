# Troubleshooting

Start with `https://your-domain/check.php` (before installing) or `php npo-workdash/artisan app:check` (any time). Both list every requirement with the setting to change in cPanel, Plesk or DirectAdmin. After installing, `https://your-domain/up` shows the health of the database, cache, storage, cron and version.

## Permissions

**Symptoms:** the wizard's permissions step fails, a "cannot write .env" message appears, or pages show a blank 500 error after installing.

PHP must be able to write to:

- `npo-workdash/shared/` (where `.env` is created)
- `npo-workdash/shared/storage/` and everything inside it
- `npo-workdash/releases/` (updates unpack here)
- `npo-workdash/releases/X.Y.Z/bootstrap/cache/`

In your file manager, set folders to `755` and files to `644`. If your host runs PHP as a different user from your FTP account (less common now), use `775` for the folders above, or ask your host to "run PHP as my account" (suPHP, PHP-FPM pools or CloudLinux).

Never use `777`.

## 500 errors

1. Look at the newest file in `npo-workdash/shared/storage/logs/`. It names the problem.
2. Common causes:
   - **Wrong PHP version**: the site is still on PHP 7 or 8.1 or 8.2. Choose 8.3 for the domain (see your panel's guide).
   - **Missing extension**: `pdo_mysql`, `zip` or `mbstring` are not enabled.
   - **`.htaccess` ignored**: on Plesk, enable `.htaccess` support. On nginx-only hosts, ask for Laravel-style rewrites (`try_files $uri /index.php?$query_string`).
   - **App folder not found**: "cannot find its application folder" means `workdash-path.php` in the web folder does not point at `npo-workdash`. Edit it to return the full path, for example `return '/home/charity/npo-workdash';`.
   - **Database moved or password changed**: edit `DB_*` in `npo-workdash/shared/.env`.
3. Do not set `APP_DEBUG=true` on a live site except briefly to read an error, because it shows secrets to visitors.

## Cron uses the wrong PHP

**Symptoms:** Super Admins see "Scheduled tasks are not running", `/up` reports `scheduler` as failing, emails are not sent, or the cron email (if your host sends one) shows a PHP parse error.

In cron, plain `php` is often an older default version, not the one your site uses. Use the full path the wizard (or **Installation** in the Super Admin area) shows. Typical paths:

| Panel | PHP 8.3 command-line path |
| --- | --- |
| cPanel (EasyApache) | `/opt/cpanel/ea-php83/root/usr/bin/php` |
| cPanel (CloudLinux alt-php) | `/opt/alt/php83/usr/bin/php` |
| Plesk | `/opt/plesk/php/8.3/bin/php` |
| DirectAdmin | `/usr/local/php83/bin/php` |

Also check:

- The line runs **every minute** (`* * * * *`).
- It points at `npo-workdash/artisan`, the stable path that keeps working after updates, not `releases/X.Y.Z/artisan`.
- The command is the whole line, ending with `schedule:run >> /dev/null 2>&1`.

The warning clears within a minute of cron running correctly.

## Updates

- **"This copy was not installed from a release zip"**: the site was set up from a git checkout. Deploy new versions the same way instead, or reinstall from the zip.
- **"Not enough disk space"**: free about four times the release size. Old backups are in `shared/storage/app/backups/`.
- **"The update server withheld the download link"**: your update server requires a licence key. Set `UPDATES_LICENCE_KEY` in `shared/.env`.
- **The update stopped part-way**: open **Updates** again (or run `app:update`) and it resumes. If it failed, it has already rolled back. The update log explains why.
