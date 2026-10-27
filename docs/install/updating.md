# Updating NPO.WorkDash

Updates are always started by a Super Admin; nothing installs itself. Super Admins get an email when a new release is out on their channel (`stable` by default; set `UPDATES_CHANNEL=beta` in `shared/.env` for pre-releases).

## From the browser

1. Sign in as a Super Admin and open **Updates** in the sidebar.
2. Read the changes and the pre-flight checks: version path, PHP, extensions, disk space and folder permissions.
3. Enter your password and two-factor code and click **Update now**.
4. Keep the page open. Each step takes a few seconds:
   1. back up the database and files
   2. download
   3. check the signature
   4. unpack
   5. maintenance mode on
   6. switch to the new version
   7. update the database
   8. health check
   9. maintenance mode off
5. If the page closes or times out, open **Updates** again and the update continues where it stopped.

> Screenshot: Updates page during an update.

## From a terminal

```bash
php npo-workdash/artisan app:update --check   # what is available
php npo-workdash/artisan app:update           # install it
```

## If something goes wrong

- A failed health check, or any failure after the switch, **rolls back automatically**: the previous version comes back, and the database is restored from the pre-update backup if it was changed. Super Admins get an email with a link to the update log.
- To undo a finished update, use **Roll back** on the Updates page, or run `php npo-workdash/artisan app:rollback`.
- Backups are in `npo-workdash/shared/storage/app/backups/` (the last three are kept). Logs are in `npo-workdash/shared/storage/logs/updates/`.

## How updates stay safe on shared hosting

- Every download is checked against its SHA-256 and an Ed25519 signature before anything is unpacked.
- Each step fits inside a 30-second PHP limit. Database changes run one per request.
- Switching versions rewrites one small file (`current.php`), so it needs neither symlinks nor shell access.
- The previous version is kept until the next successful update.
