# Installing NPO.WorkDash

NPO.WorkDash installs from one release zip on shared hosting with a control panel, on Cloudron, or on your own server (VPS). The zip already contains everything PHP needs, so you do not need Composer, Node or a terminal on shared hosting.

Pick the guide for your hosting:

| Hosting | Guide |
| --- | --- |
| cPanel (most UK shared hosts) | [cpanel.md](cpanel.md) |
| Plesk | [plesk.md](plesk.md) |
| DirectAdmin | [directadmin.md](directadmin.md) |
| Cloudron (LAMP app) | [cloudron.md](cloudron.md) |
| Your own server (VPS) | [vps.md](vps.md) |

Then see [updating.md](updating.md) for updates and [troubleshooting.md](troubleshooting.md) if something goes wrong. Hosting plans that have been tested are listed in [compatibility.md](compatibility.md).

## What you need

- PHP 8.3 or newer, with these extensions: ctype, curl, dom, fileinfo, filter, hash, mbstring, openssl, pcre, pdo, pdo_mysql, session, tokenizer, xml and zip.
- MySQL 8.0+ or MariaDB 10.6+, with an empty database and a user that has all privileges on it.
- About 500 MB of free disk space (the app, one previous release for rollback, downloads and three backups).
- HTTPS for the domain. Every control panel can issue a free Let's Encrypt certificate.
- A cron job that runs every minute. The wizard shows the exact line.

## Download and check the release

1. Open the [releases page](https://github.com/APESCIC/NPO.WorkDash/releases) and download `npo-workdash-X.Y.Z.zip` from the newest release that is not marked "Pre-release".
2. Optional but recommended: check the download. On a computer with a terminal, run `sha256sum --check npo-workdash-X.Y.Z.zip.sha256` (download the `.sha256` file too). The installer and updater check the signature of every update themselves.

## What is in the zip

```text
npo-workdash/
  current.php          names the live release (updates change only this file)
  artisan              runs commands for the live release (used by cron)
  public/              the web folder: point the site here, or copy it into public_html
  releases/X.Y.Z/      the application
  shared/storage/      files, logs and backups kept across updates (.env is created here)
```

Keep `npo-workdash/` **outside** the web folder. Only the contents of `public/` are meant to be reachable from the web, and its `.htaccess` blocks hidden files.

## The two web-folder layouts

- **Point the domain at `npo-workdash/public`** (best). Most control panels let you set a domain's or subdomain's "document root".
- **Fixed `public_html`.** If your plan cannot change the document root, upload `npo-workdash/` next to `public_html` and copy everything inside `npo-workdash/public/` into `public_html/`. The same works for a subdomain folder such as `public_html/charity`. If `npo-workdash` is somewhere else, edit `workdash-path.php` in the web folder so it returns the full path of the `npo-workdash` folder.

## The install wizard

Open `https://your-domain/check.php` first. It lists anything your hosting needs to change, with the setting to look for in cPanel, Plesk or DirectAdmin. Then open `https://your-domain/install`. The wizard:

1. checks the server and folder permissions
2. connects to the database (it warns if the database is not empty)
3. sets up the tables, one step at a time with a progress bar
4. asks for the site address, timezone and email (SMTP) settings, and sends a test email
5. creates your **Super Admin** account. There is no default password: choose one with at least 12 characters.
6. creates your charity's first workspace
7. shows the cron line to add
8. locks itself. Afterwards every install page returns "not found", and you can delete `check.php`.

You can refresh or close the browser at any point; the wizard carries on from the step you were on.

Sign in with the Super Admin account. You will be asked to set up two-factor authentication (an authenticator app on your phone) before you can use the app.
