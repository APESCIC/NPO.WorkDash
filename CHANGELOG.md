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

[Unreleased]: https://github.com/APESCIC/NPO.WorkDash/compare/v0.2.0-beta.1...HEAD
[0.2.0-beta.1]: https://github.com/APESCIC/NPO.WorkDash/compare/v0.1.2...v0.2.0-beta.1
[0.1.2]: https://github.com/APESCIC/NPO.WorkDash/compare/v0.1.1...v0.1.2
[0.1.1]: https://github.com/APESCIC/NPO.WorkDash/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/APESCIC/NPO.WorkDash/releases/tag/v0.1.0
