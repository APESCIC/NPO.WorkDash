# Architecture decision records

Significant technical decisions for NPO.WorkDash are recorded here as ADRs. Each ADR is short, numbered and immutable once accepted: to change a decision, add a new ADR that supersedes the old one and update the old one's status.

## Index

| ADR | Title | Status |
| --- | --- | --- |
| [0001](0001-stack.md) | Application stack: Laravel 12, React/Inertia, MySQL 8 | Accepted |
| [0002](0002-tenancy.md) | Multi-tenancy: single database with `tenant_id` | Accepted |
| [0003](0003-billing.md) | Subscription billing provider: Stripe Billing | Accepted |
| [0004](0004-hosting.md) | Hosting: Cloudron LAMP app | Accepted |
| [0005](0005-clean-room.md) | Clean-room build with no WorkDo code | Accepted |
| [0006](0006-migrations.md) | Database migrations: expand and contract, one at a time | Accepted |

## Statuses

- **Proposed**: under discussion, not yet binding.
- **Accepted**: agreed and in force.
- **Superseded by NNNN**: replaced by a later ADR.
- **Deprecated**: no longer relevant, not replaced.

## Template

Copy this into `docs/adr/NNNN-short-title.md`, using the next free number.

```markdown
# NNNN. Title

- Status: Proposed
- Date: YYYY-MM-DD
- Deciders: names

## Context

What problem are we solving, and what forces (constraints, requirements, risks) apply?

## Decision

What we decided, stated plainly.

## Alternatives considered

- **Option**: why it was not chosen.

## Consequences

What becomes easier or harder as a result, including follow-up work and risks.
```
