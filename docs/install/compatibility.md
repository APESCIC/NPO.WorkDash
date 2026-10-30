# Hosting compatibility

## Tested automatically

Every change to the installer or updater runs the [Compatibility workflow](../../.github/workflows/compat.yml). It builds a signed release zip and a newer one, installs the first on Apache with `app:install` in three web-folder layouts (document root at `public/`, a fixed `public_html`, and a subdomain folder), checks that `.env`, storage and code cannot be reached over HTTP, then updates to the newer zip with `app:update` and checks `/up`.

| PHP | MySQL 8.0 | MariaDB 10.6 | MariaDB 10.11 |
| --- | --- | --- | --- |
| 8.3 | Yes | Yes | Yes |
| 8.4 | Yes | Yes | Yes |

A restricted-PHP job repeats the install and update with shared-hosting limits:

- `max_execution_time=30`
- `memory_limit=256M`
- `exec`, `proc_open`, `shell_exec`, `system`, `passthru`, `popen` and `symlink` disabled

It drives the update one step per PHP process, as the web updater does, so each step must fit the 30-second limit.

## Tested by hand

Manual install and update passes on real hosting plans, recorded before 1.0 ([#214](https://gitea.intranet.apes.org.uk/APESCIC/NPO.WorkDash/issues/214)):

| Panel | Host and plan | PHP | Database | Date | Result |
| --- | --- | --- | --- | --- | --- |
| cPanel | Not yet tested | | | | |
| Plesk | Not yet tested | | | | |
| DirectAdmin | Not yet tested | | | | |
