# Changelog

All notable changes to NPO.WorkDash are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Release convention

1. Move the entries under **Unreleased** into a new `## [X.Y.Z] - YYYY-MM-DD` section, grouped as Added, Changed, Deprecated, Removed, Fixed or Security.
2. Set `VERSION` to the same `X.Y.Z`. CI fails if `VERSION` is not valid semver.
3. Merge to `main`, then tag the merge commit `vX.Y.Z` and create a GitHub release with the changelog section as its notes.
4. Deploy the tag to staging, then production (see the deployment guide).

While the major version is 0, bump the minor version for new features or breaking changes and the patch version for fixes.

## [Unreleased]

## [0.1.0] - 2026-10-27

### Added

- Laravel 12 application with React (Inertia, TypeScript, Tailwind) and the authentication starter kit: registration, email verification, login, password reset and two-factor authentication. UK locale (`en_GB`) and `Europe/London` timezone.
- Pest test harness against MySQL, with factories and a coverage report.
- GitHub Actions CI: Pint, Larastan, Pest on MySQL 8.0, ESLint, Prettier, TypeScript and the Vite build.
- Local development with Laragon, Vite hot module replacement, Mailpit and a local-only demo seeder.
- Architecture decision records for the stack, tenancy, billing, hosting and the clean-room build.
- `VERSION` file and this changelog. The version is shown in the app sidebar.

[Unreleased]: https://github.com/APESCIC/NPO.WorkDash/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/APESCIC/NPO.WorkDash/releases/tag/v0.1.0
