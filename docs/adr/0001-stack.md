# 0001. Application stack: Laravel 12, React/Inertia, MySQL 8

- Status: Accepted
- Date: 2026-10-27
- Deciders: Bambie Murphy

## Context

NPO.WorkDash is a hosted SaaS for UK charities and CICs covering fundraising, compliance (Gift Aid, GDPR), people, projects, animal care, a helpdesk and subscription billing. It needs authentication, roles, queues, scheduled jobs, PDF generation and email out of the box. It must run on the Cloudron LAMP app (see [ADR 0004](0004-hosting.md)) and match the patterns already used in the Dash family of apps so the same people can maintain it.

## Decision

- **Backend**: Laravel 12 on PHP 8.3, using the official React starter kit (Fortify authentication).
- **Frontend**: React 19 with TypeScript and Tailwind CSS, served through Inertia.js, so there is no separate API for the first-party UI.
- **Database**: MySQL 8.0 (utf8mb4). Local development uses Laragon's MySQL 8.4, so 8.4-only SQL is avoided.
- **Build**: Vite with hot module replacement.
- **Tests**: Pest (with the Laravel plugin) for unit and feature tests, against MySQL rather than SQLite so behaviour matches production.
- **Quality**: Laravel Pint, Larastan, ESLint, Prettier and `tsc` in CI.

## Alternatives considered

- **Next.js (Node) with a separate API**: strong React story, but needs a Node runtime that the Cloudron LAMP app does not provide. It would also mean rebuilding queues, scheduling, auth and PDFs from separate libraries.
- **Django**: batteries included and a good admin, but a Python runtime is not available on the LAMP app and it does not match the team's existing Laravel experience.
- **Laravel with Livewire or Blade only**: simpler, but the module-heavy dashboard UI benefits from a component library and TypeScript, and the React starter kit gives that with no extra API layer.
- **PostgreSQL**: technically attractive, but the Cloudron LAMP app pairs with the MySQL addon.

## Consequences

- One PHP runtime handles web, queue worker and scheduler, which keeps the Cloudron deployment simple.
- Frontend and backend live in one repository and deploy together, and Inertia shared props replace a public API for now. A public or partner API, if needed later, will need its own decision.
- Tests need a MySQL test database locally and in CI.
