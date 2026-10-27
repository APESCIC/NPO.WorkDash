# Install on DirectAdmin

Read the [overview](README.md) first for what you need and what is in the zip. Menu names are from DirectAdmin's Evolution skin.

## 1. Choose PHP 8.3

1. Open **Domain Setup**, choose the domain, and set the **PHP Version Selector** to **8.3** (on CloudLinux: **Select PHP Version**).
2. Enable the `pdo_mysql`, `zip`, `mbstring`, `intl`, `fileinfo` and `curl` extensions.
3. Ask your host to set `memory_limit = 256M` and `max_execution_time = 30` or more if you cannot change them yourself.

> Screenshot: PHP version selector for the domain.

## 2. Create the database

1. Open **MySQL Management > Create new Database**.
2. Enter a name and let DirectAdmin generate the user and password.
3. Note the full names. DirectAdmin prefixes them with your user name, for example `charity_workdash`.

## 3. Upload the files

1. Open **File Manager** and go to `domains/your-domain/`, next to `public_html`.
2. Upload `npo-workdash-X.Y.Z.zip` and choose **Extract**. You now have `domains/your-domain/npo-workdash/`.

## 4. Point the site at the web folder

DirectAdmin usually fixes the web folder to `public_html`:

1. Open `npo-workdash/public`.
2. Select everything, including the hidden `.htaccess`, and **Copy** it to `public_html`.

The site finds `npo-workdash` next to `public_html` by itself.

If your host lets you change the document root (**Domain Setup > Custom document root**), set it to `npo-workdash/public` instead.

Request a certificate under **SSL Certificates > Free & automatic certificate from Let's Encrypt**.

> Screenshot: public_html after copying the web folder contents.

## 5. Run the wizard

Open `https://your-domain/check.php`, fix anything marked FAIL, then open `https://your-domain/install`.

## 6. Add the cron job

1. Open **Cron Jobs**.
2. Set minute, hour, day, month and weekday to `*`.
3. Paste the command the wizard showed, for example:

   ```text
   /usr/local/php83/bin/php /home/charity/domains/example.org/npo-workdash/artisan schedule:run >> /dev/null 2>&1
   ```

> Screenshot: Cron Jobs form with every field set to *.

## 7. Your first update

Sign in as the Super Admin, open **Updates**, and click **Update now**. See [updating.md](updating.md).
