# 0006. Database migrations: expand and contract, one at a time

- Status: Accepted
- Date: 2026-10-27
- Deciders: Bambie Murphy

## Context

NPO.WorkDash updates itself on shared hosting with short PHP time limits (often 30 seconds), and both the updater and `rollback.sh` can switch back to the previous release. A rollback switches code instantly, but the database only goes back by restoring the pre-update backup. Data changes also have to respect tenant isolation ([ADR 0002](0002-tenancy.md)) and stay small enough to finish inside one request.

## Decision

1. **Expand, then contract.** A schema change ships in two releases:
   - **Expand** (release N): add new tables, nullable columns or columns with defaults, and new indexes. The release N-1 code must keep working against the expanded schema.
   - **Contract** (release N+1 or later): remove or rename what release N stopped using, once no supported release reads it.
2. **No destructive change in the same release as the code change that needs it.** Dropping or renaming a column, or narrowing a type, never ships alongside the code that stops using it. It waits for a later release, so rolling back the code by one release is always safe without touching the database.
3. **One migration per request.** The installer and the updater run migrations one file per request or step. Each migration must finish well inside 20 seconds on a large tenant, so long work goes in a backfill.
4. **Backfills are tenant-chunked.** Data backfills use `App\Support\TenantBackfill::each()`. It processes one `tenant_id` at a time in id-ordered chunks, and must be idempotent, so a killed request can simply run again. Large backfills run as queued jobs that call `TenantBackfill` from where the last job stopped.
5. **Self-contained migrations.** Migrations use the schema builder and query builder only, never application models, because the updater may run them before the new code is fully loaded.
6. **MySQL 8.0 and MariaDB 10.6+.** Migrations must run on MySQL 8.0, MariaDB 10.6 and MariaDB 10.11. CI runs every migration on all three against seeded multi-tenant data, then rolls back and migrates again.

## Alternatives considered

- **Big-bang migrations with `migrate --force` on deploy**: simple, but they time out on shared hosts and make code rollbacks unsafe.
- **Always restore the database on rollback**: it loses every write made since the update, so it is the last resort, used only when migrations ran during a failed update.

## Consequences

- Removing a column takes two releases. Reviewers check that a migration's `down()` is safe and that no destructive change ships with its code change.
- Rollback is a code switch in the normal case, and the backup restore stays an exception.
- The updater can report progress per migration, and a host with a 30-second limit can apply any release.
