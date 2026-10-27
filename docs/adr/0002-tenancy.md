# 0002. Multi-tenancy: single database with `tenant_id`

- Status: Accepted
- Date: 2026-10-27
- Deciders: Bambie Murphy

## Context

NPO.WorkDash is a hosted multi-tenant SaaS: each charity gets its own workspace, and APES is tenant #1. Donor, volunteer and animal records are personal or sensitive data under UK GDPR, so one charity must never see another's data. The platform runs on a single Cloudron LAMP app with one MySQL addon database (see [ADR 0004](0004-hosting.md)), and Super Admins need cross-tenant reporting (revenue, usage).

## Decision

Use a **single shared database**. Every tenant-owned table has a non-null, indexed `tenant_id` foreign key to the workspace.

- A `BelongsToTenant` model trait applies a global scope that filters every query by the current tenant, and fills `tenant_id` on create.
- The current tenant is resolved once per request (and per queued job) and stored in a tenant context. Queries without a tenant context fail closed rather than returning all rows.
- Bypassing the scope (`withoutGlobalScope`) is only allowed in Super Admin and system code, and is reviewed.
- Unique indexes on tenant data include `tenant_id`.
- Every tenant-owned model gets an automated isolation test proving tenant A cannot read or write tenant B's records.

## Alternatives considered

- **Database per tenant**: the strongest physical separation, but the Cloudron MySQL addon provides one database per app. Every migration would have to run N times, and cross-tenant reporting gets harder. Reconsider if a customer contractually requires physical separation.
- **Schema per tenant**: MySQL treats schemas as databases, so this has the same drawbacks as database per tenant.
- **Separate app instance per charity**: simple isolation, but operational cost grows linearly and it does not fit a self-serve signup SaaS.

## Consequences

- One migration run, one backup and one connection pool, which keeps Cloudron operations simple.
- Isolation depends on application code, so the global scope, the fail-closed tenant context and the isolation tests are mandatory for every new module. A missed scope is a data breach.
- Very large tenants share capacity with everyone else. Indexing on `tenant_id` and monitoring are needed as the platform grows.
- If physical separation is ever required, a new ADR will supersede this one.
