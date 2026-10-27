# Install on cPanel

Read the [overview](README.md) first for what you need and what is in the zip. Menu names below are from cPanel's default Jupiter theme; your host may group them differently.

## 1. Choose PHP 8.3

1. Open **MultiPHP Manager** (some hosts call it **Select PHP Version**).
2. Tick your domain, choose **PHP 8.3** (or newer) and click **Apply**.
3. In **Select PHP Version > Extensions** (CloudLinux hosts), make sure `pdo_mysql`, `zip`, `mbstring`, `intl`, `fileinfo` and `curl` are ticked.
4. In **MultiPHP INI Editor**, set `memory_limit` to `256M` and `max_execution_time` to at least `30`.

> Screenshot: MultiPHP Manager with PHP 8.3 selected for the domain.

## 2. Create the database

1. Open **MySQL Database Wizard**.
2. Create a database (for example `charity_workdash`), then a user with a long generated password.
3. On the privileges step, tick **ALL PRIVILEGES**.
4. Note the full database name and user name. cPanel adds your account name as a prefix, for example `charity_workdash`.

> Screenshot: MySQL Database Wizard, privileges step.

## 3. Upload the files

1. Open **File Manager** and go to your home folder, the one that contains `public_html`. Do not open `public_html` itself.
2. Click **Upload** and upload `npo-workdash-X.Y.Z.zip`.
3. Back in File Manager, right-click the zip and choose **Extract**. You now have a `npo-workdash` folder next to `public_html`.
4. Delete the zip.

> Screenshot: File Manager showing npo-workdash next to public_html.

## 4. Point the site at the web folder

**Option A (recommended):** open **Domains**, click **Manage** for the domain (or create a subdomain), and set the **Document Root** to `npo-workdash/public`.

**Option B (main domain fixed to `public_html`):**

1. Open `npo-workdash/public` in File Manager.
2. Tick **Show Hidden Files** in Settings, so `.htaccess` is visible.
3. Select all, choose **Copy**, and copy into `public_html` (or a subdomain folder inside it).

The site finds `npo-workdash` next to `public_html` by itself.

Then open **SSL/TLS Status** and run **AutoSSL** for the domain if it does not have a certificate yet.

## 5. Run the wizard

1. Open `https://your-domain/check.php` and fix anything marked FAIL. Each item shows the cPanel setting to change.
2. Open `https://your-domain/install` and follow the steps. Use `localhost` as the database host unless your host says otherwise.

## 6. Add the cron job

1. Open **Cron Jobs**.
2. Under **Add New Cron Job**, choose **Once Per Minute** (`* * * * *`).
3. Paste the command the wizard showed, for example:

   ```text
   /opt/cpanel/ea-php83/root/usr/bin/php /home/charity/npo-workdash/artisan schedule:run >> /dev/null 2>&1
   ```

4. Use the PHP path the wizard shows. Plain `php` in cron is often an older version (see [troubleshooting](troubleshooting.md#cron-uses-the-wrong-php)).

> Screenshot: Cron Jobs with the once-per-minute line added.

## 7. Your first update

When a new version is released, Super Admins get an email. Sign in, open **Updates** in the sidebar, read the changes and click **Update now** (you will be asked for your password and two-factor code). Keep the page open until it finishes. See [updating.md](updating.md).
