# Changelog

All notable changes to NPO.WorkDash are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Release convention

1. Move the entries under **Unreleased** into a new `## [X.Y.Z] - YYYY-MM-DD` section, grouped as Added, Changed, Deprecated, Removed, Fixed or Security.
2. Set `VERSION` to the same `X.Y.Z`. CI fails if `VERSION` is not valid semver.
3. Merge to `main`, then tag the merge commit `vX.Y.Z`. The Release workflow builds and signs the zip and publishes the GitHub release with this section as its notes. Pre-release tags (`vX.Y.Z-beta.N`) go to the beta channel only.
4. Deploy the tag to staging, then production (see the deployment guide).

While the major version is 0, bump the minor version for new features or breaking changes and the patch version for fixes.

## [Unreleased]

## [0.2.1] - 2026-10-30

No changes to the application: installs and updates behave exactly as in 0.2.0.

### Changed

- GitHub is again the only host for code, issues, milestones, pull requests, CI and releases. A same-day switch of `AGENTS.md`, the Cursor rules and the docs to Gitea was reverted before release, and GitHub issues are turned back on.

## [0.2.0] - 2026-10-27

First stable release with install and updates: NPO.WorkDash now installs from one signed zip on shared hosting, Cloudron or a VPS, and updates itself from the browser or the command line. It includes everything from 0.2.0-beta.1, beta.2 and beta.3 below.

### Added

- Update emails to Super Admins: once per new release on their channel (a daily check that never installs anything), and when an update succeeds, fails or is rolled back. The emails give the from and to versions, who started the update, how long it took, the reason for any failure, and a link to the update log. Logs live in `storage/logs/updates/` and can be read from the Updates page.
- Install and update guides in `docs/install/` for cPanel, Plesk, DirectAdmin, Cloudron and VPS, covering upload, database, web folder, cron and the first update, with troubleshooting for permissions, 500 errors and cron using the wrong PHP. The wizard links to the right guide.
- Compatibility workflow. It installs a signed release zip and updates it to a newer one on PHP 8.3 and 8.4 against MySQL 8.0, MariaDB 10.6 and MariaDB 10.11, in the docroot, `public_html` and subdomain layouts. A restricted-PHP job repeats this with a 30-second time limit, 256 MB of memory, and `exec`, `proc_open` and `symlink` disabled.
- `php artisan app:update --step` runs one update step and exits, the same unit of work as one web request.

## [0.2.0-beta.3] - 2026-10-27

### Added

- Resumable update engine. An update is an ordered list of idempotent steps stored in the database:
  1. pre-flight
  2. backup
  3. download
  4. verify
  5. extract
  6. maintenance on
  7. swap
  8. migrate
  9. health check
  10. maintenance off
  11. clean up

  Each request works on one step for at most 15 seconds, so updates fit a 30-second PHP limit. A killed request or closed browser resumes from the last unfinished step, a file lock stops two updates running at once, and every step is logged to `storage/logs/updates/`.
- Pre-update backup in pure PHP: a chunked SQL dump (no `mysqldump` needed) plus a zip of `.env` and `storage/app`, stored outside the web root. The last 3 are kept.
- Chunked, resumable release download. The SHA-256 and Ed25519 signature are checked before extraction, and zips with `../` or absolute paths are rejected. Low disk space aborts before anything changes, and extraction runs in batches into `releases/<version>` and is checked against the manifest.
- Release swap and rollback:
  - On shared hosting, the swap rewrites `current.php` (temp file, then rename) and copies the new web assets. It needs neither symlinks nor `exec`.
  - On Cloudron and VPS, the swap switches the release symlink.
  - Rollback restores the previous release and, if migrations ran, the database backup. The previous release is kept until the next successful update.
- Super Admin updater at **Updates**:
  - Shows the version, channel, available release, changelog, pre-flight results, live progress and history.
  - Starting an update or a rollback needs the password and a two-factor code again.
  - Requests are CSRF-protected and rate limited.
  - Updates never start on their own.
- `php artisan app:update`, with `--check` and resume support, and `php artisan app:rollback`. They use the same engine as the web updater, exit non-zero on failure and restart a long-running queue worker.
- `/up` health check: database, cache, writable storage, scheduler heartbeat and version. It returns JSON with 200 only when every check passes, otherwise 503. The updater leaves maintenance mode only after it passes, and rolls back automatically if it fails.
- `App\Support\TenantBackfill` processes data backfills one `tenant_id` at a time in small chunks. ADR 0006 records the expand-and-contract migration rule.
- CI runs every migration on MySQL 8.0, MariaDB 10.6 and MariaDB 10.11 with seeded multi-tenant data, then rolls back and migrates again.

### Fixed

- The web install wizard is off on Cloudron, where an app is reachable as soon as it is deployed. Uninstalled Cloudron apps now explain how to install with `php artisan app:install` instead.

## [0.2.0-beta.2] - 2026-10-27

### Added

- Web install wizard at `/install` for hosts with only a browser and a file manager. Its steps: welcome, server requirements, folder permissions, database (tests the connection, version and charset, and warns when the database is not empty), migrations (one per request, with a progress bar), site address and timezone, email (with an SMTP test), Super Admin, first workspace, cron and finish. Each step stays well inside a 30-second PHP limit, and refreshing the browser resumes at the current step.
- `php artisan app:install`, with prompts or `--no-interaction` flags, running the same installer service as the wizard. On Cloudron it reads the MySQL addon variables.
- One requirements checker shared by a standalone `check.php`, the wizard and `php artisan app:check`. It reports pass, warn or fail for PHP 8.3+, extensions, disabled functions, database version and charset, writable folders, memory, execution time, disk space, HTTPS, URL rewriting and outbound HTTPS, with cPanel, Plesk and DirectAdmin hints. MySQL below 8.0 and MariaDB below 10.6 fail.
- Release layout in the zip:
  - `npo-workdash/public/` is the web root (or its contents go in a fixed `public_html`, including a subdomain folder).
  - `current.php` is the only file an update rewrites.
  - `.env` and storage live in `shared/` and are kept across releases.
  - A stable `artisan` proxy serves cron.
  - Dotfiles and layout helpers are denied over HTTP.
- Super Admins, with a `super-admin` gate and `php artisan app:super-admin <email>`. Super Admin passwords need at least 12 characters and are checked against known breaches when online. Super Admins are sent to set up two-factor authentication before using the app.
- Workspaces table, with workspace 1 created at install. There are no default accounts or passwords.
- The installer locks after use: a `storage/installed.lock` file plus an `installations` record. Every `/install` route returns 404, deleting the lock file alone does not reopen it, `app:install` refuses to run again, and the finish screen offers to delete the installer files.
- Shared-hosting background jobs: with `QUEUE_RUNNER=cron` (the default outside Cloudron), the per-minute scheduler runs `queue:work --stop-when-empty`. The wizard shows the exact cron line for the detected PHP binary. Super Admins see a warning when the scheduler has not run for 10 minutes, and the new Installation page in the Super Admin area shows its status. Cloudron keeps its supervised worker.

### Changed

- Production databases that already have users are marked as installed, with workspace 1, by a migration, so existing installs never see the wizard.
- The release smoke test installs the zip with Apache in the docroot, `public_html` and subdomain-folder layouts, and checks that `.env`, storage and code cannot be reached over HTTP.

## [0.2.0-beta.1] - 2026-10-27

### Added

- Release workflow: every `vX.Y.Z` tag builds `npo-workdash-X.Y.Z.zip` with `vendor/` and `public/build` included (no `.env`, tests, `node_modules` or dev tooling). It fails if `VERSION` does not match the tag, and installs the zip on a plain PHP container without Composer or Node and loads `/login` before publishing.
- Release manifest (`manifest.json`) with version, channel, release date, minimum PHP, MySQL 8.0 and MariaDB 10.6, required extensions, minimum version to update from, migration list and a SHA-256 per file. The zip is published with a `.sha256` checksum and an Ed25519 `.sig` signature, and `php artisan release:verify` checks both against the release key.
- Stable and beta update feeds on GitHub Pages (`feed/v1/stable.json` and `beta.json`), validated against a versioned JSON schema. Pre-release tags (`-beta.N`) appear only in the beta feed and are published as GitHub pre-releases.
- Licence-gated update downloads, built and switched off: with `UPDATES_LICENCE_REQUIRED=true`, the app's `/updates/feed/{channel}` endpoint withholds download links from installations without a valid licence key. Installations send their licence key and installation ID with feed requests.
- Release signing and key rotation guide in `docs/security/release-signing.md`.

### Changed

- Releases are published by the Release workflow instead of `gh release create`. `build-release.sh` and the release zip share `deploy/release/stage.sh`.

## [0.1.2] - 2026-10-27

### Added

- `AGENTS.md` and Cursor rules for the work lifecycle: issues, milestones and sub-issues kept current, and every change committed, merged, released and deployed, with next options and a recommendation.
- `deploy/cloudron/cloudron-deploy.sh`: one command to build a tagged release, deploy it to the production Cloudron app with the Cloudron CLI and verify `/up` and the live revision. `--bootstrap` prepares a fresh LAMP app.
- Pull request template and issue templates for tasks and bugs.

### Fixed

- `build-release.sh` names the archive and `REVISION` after the commit an annotated tag points to, not the tag object.

## [0.1.1] - 2026-10-27

### Fixed

- The Cloudron app no longer crash-loops on start. The LAMP image sources `run.sh` under `set -eu`, and `run.sh` assigned the image's readonly `php_version` variable, which stopped the start script before Apache. `run.sh` now runs in a subshell, so it cannot abort the start script, and its queue worker and scheduler loops keep restarting after a non-zero exit. The deployment guide explains this and how to recover a crash-looping app.

## [0.1.0] - 2026-10-27

### Added

- Laravel 12 application with React (Inertia, TypeScript, Tailwind) and the authentication starter kit: registration, email verification, login, password reset and two-factor authentication. UK locale (`en_GB`) and `Europe/London` timezone.
- Pest test harness against MySQL, with factories and a coverage report.
- GitHub Actions CI: Pint, Larastan, Pest on MySQL 8.0, ESLint, Prettier, TypeScript and the Vite build.
- Local development with Laragon, Vite hot module replacement, Mailpit and a local-only demo seeder.
- Architecture decision records for the stack, tenancy, billing, hosting and the clean-room build.
- `VERSION` file and this changelog. The version is shown in the app sidebar.
- Documented environment configuration with Cloudron MySQL and mail addon fallbacks, gitleaks secret scanning in CI, and a secrets handling guide.
- Cloudron LAMP deployment: release build, deploy and rollback scripts, `run.sh` supervising the queue worker and scheduler, queue and scheduler heartbeats, and a deployment guide for the production app at npo-workdash.apes.org.uk.

[Unreleased]: https://github.com/APESCIC/NPO.WorkDash/compare/v0.2.1...HEAD
[0.2.1]: https://github.com/APESCIC/NPO.WorkDash/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/APESCIC/NPO.WorkDash/compare/v0.1.2...v0.2.0
[0.2.0-beta.3]: https://github.com/APESCIC/NPO.WorkDash/compare/v0.2.0-beta.2...v0.2.0-beta.3
[0.2.0-beta.2]: https://github.com/APESCIC/NPO.WorkDash/compare/v0.2.0-beta.1...v0.2.0-beta.2
[0.2.0-beta.1]: https://github.com/APESCIC/NPO.WorkDash/compare/v0.1.2...v0.2.0-beta.1
[0.1.2]: https://github.com/APESCIC/NPO.WorkDash/compare/v0.1.1...v0.1.2
[0.1.1]: https://github.com/APESCIC/NPO.WorkDash/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/APESCIC/NPO.WorkDash/releases/tag/v0.1.0
