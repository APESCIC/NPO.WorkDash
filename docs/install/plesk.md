# Install on Plesk

Read the [overview](README.md) first for what you need and what is in the zip. Menu names are from Plesk Obsidian.

## 1. Choose PHP 8.3

1. Open **Websites & Domains**, then the domain, then **PHP** (or **PHP Settings**).
2. Choose **PHP 8.3** (FPM application served by Apache or nginx).
3. Set `memory_limit` to `256M` and `max_execution_time` to at least `30`, then click **OK**.

> Screenshot: Plesk PHP settings for the domain.

## 2. Create the database

1. Open **Databases > Add Database**.
2. Enter a name (for example `workdash`), choose the MariaDB or MySQL server, and create a user with a generated password and **full access**.
3. Note the database name, user and host. The host is usually `localhost`.

> Screenshot: Add Database form.

## 3. Upload the files

1. Open **Files**.
2. Go to the home folder: the one that contains `httpdocs`, not `httpdocs` itself.
3. Upload `npo-workdash-X.Y.Z.zip`, then click the zip and choose **Extract Files**. You now have a `npo-workdash` folder next to `httpdocs`.

## 4. Point the site at the web folder

1. Open **Websites & Domains > Hosting & DNS > Hosting**.
2. Set **Document root** to `npo-workdash/public` and save.

If you cannot change it, copy the contents of `npo-workdash/public` (including the hidden `.htaccess`) into `httpdocs`.

In **Apache & nginx Settings**, keep **Allow .htaccess** (or "Proxy mode") on, so URL rewriting works.

Issue a free certificate under **SSL/TLS Certificates > Let's Encrypt**.

## 5. Run the wizard

Open `https://your-domain/check.php`, fix anything marked FAIL (each item shows the Plesk setting), then open `https://your-domain/install` and follow the steps.

## 6. Add the cron job

1. Open **Scheduled Tasks > Add Task**.
2. Choose **Run a command**, and set **Run** to **Cron style** with `* * * * *`.
3. Paste the command the wizard showed, for example:

   ```text
   /opt/plesk/php/8.3/bin/php /var/www/vhosts/example.org/npo-workdash/artisan schedule:run >> /dev/null 2>&1
   ```

4. Choose **Do not notify**, and save.

> Screenshot: Scheduled Tasks with the per-minute command.

## 7. Your first update

Sign in as the Super Admin, open **Updates**, and click **Update now**. See [updating.md](updating.md).
