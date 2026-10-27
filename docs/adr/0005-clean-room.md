# 0005. Clean-room build with no WorkDo code

- Status: Accepted
- Date: 2026-10-27
- Deciders: Bambie Murphy

## Context

The feature set was initially scoped by looking at a commercial ERP product from WorkDo. Its licence does not permit reusing, redistributing or offering its code as a hosted service, and its generic "beneficiaries" model does not fit UK charity needs (Gift Aid, GDPR, CIC34, animal care).

## Decision

Build NPO.WorkDash **from scratch**:

- No WorkDo source code, templates, database schemas, translations, images, icons or other assets are copied into this repository, whether verbatim or lightly modified.
- Feature ideas may be informed by publicly visible product behaviour, but the domain model, UI and code are designed independently around UK charity requirements.
- Third-party code is only added through package managers (Composer, npm) under licences compatible with a hosted commercial SaaS (for example MIT, BSD or Apache-2.0).

## Alternatives considered

- **Licensing and customising WorkDo**: faster to start, but the licence restrictions, the generic data model and the dependency on a third-party codebase rule it out.
- **Forking another open-source charity CRM (for example CiviCRM)**: mature, but a different stack, and hard to fit to our multi-tenant SaaS and UK compliance scope.

## Consequences

- More up-front build effort, offset by a model designed for UK charities and full ownership of the code.
- Reviewers should reject PRs that include code or assets of unclear origin.
- Dependency licences should be checked when new packages are added (to be automated in milestone M8).
