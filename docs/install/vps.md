# Install on your own server (VPS)

For a server you manage, for example Ubuntu 24.04 with Apache or nginx, PHP-FPM 8.3 and MySQL 8.0 or MariaDB 10.11. Read the [overview](README.md) for requirements.

## 1. Upload

```bash
cd /var/www
sudo -u www-data curl -LO https://github.com/APESCIC/NPO.WorkDash/releases/download/vX.Y.Z/npo-workdash-X.Y.Z.zip
sudo -u www-data curl -LO https://github.com/APESCIC/NPO.WorkDash/releases/download/vX.Y.Z/npo-workdash-X.Y.Z.zip.sha256
sha256sum --check npo-workdash-X.Y.Z.zip.sha256
sudo -u www-data unzip -q npo-workdash-X.Y.Z.zip && rm npo-workdash-X.Y.Z.zip*
```

## 2. Database

```sql
CREATE DATABASE workdash CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'workdash'@'localhost' IDENTIFIED BY '<a long generated password>';
GRANT ALL PRIVILEGES ON workdash.* TO 'workdash'@'localhost';
```

## 3. Web server

Point the site's document root at `/var/www/npo-workdash/public`.

- **Apache**: `AllowOverride All` for that folder, with `mod_rewrite` enabled.
- **nginx**: `root /var/www/npo-workdash/public;`, `try_files $uri $uri/ /index.php?$query_string;`, PHP passed to FPM, and `location ~ /\. { deny all; }`.

Add HTTPS with Certbot.

## 4. Install

Use the browser wizard at `https://your-domain/install`, or the terminal:

```bash
sudo -u www-data php /var/www/npo-workdash/artisan app:install
```

`app:install --no-interaction` accepts `--db-host`, `--db-database`, `--db-username`, `--db-password`, `--url`, `--admin-name`, `--admin-email`, `--admin-password` and `--workspace` for scripted installs.

## 5. Cron, or a queue worker

Add the per-minute cron for `www-data` (`sudo crontab -u www-data -e`):

```text
* * * * * /usr/bin/php8.3 /var/www/npo-workdash/artisan schedule:run >> /dev/null 2>&1
```

The scheduler also drains the queue. If you would rather run a supervised worker (systemd or Supervisor running `php artisan queue:work`), set `QUEUE_RUNNER=worker` in `npo-workdash/shared/.env` so the scheduler does not start a second one.

## 6. Your first update

```bash
sudo -u www-data php /var/www/npo-workdash/artisan app:update --check
sudo -u www-data php /var/www/npo-workdash/artisan app:update
```

Or use **Updates** in the app. See [updating.md](updating.md).
