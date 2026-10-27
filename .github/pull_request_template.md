## Summary

<!-- What changes and why, in a few sentences. -->

Closes #

## Changes

-

## Verification

<!-- Commands run and what they showed. CI runs the same checks. -->

- [ ] `composer ci:check`
- [ ] `npm run build` (if the frontend changed)
- [ ] Tests added or updated for the behaviour change (isolation tests for tenant-owned models)

## Release

- [ ] `CHANGELOG.md`: entry moved into `## [X.Y.Z] - YYYY-MM-DD` and compare links updated
- [ ] `VERSION` set to `X.Y.Z` (0.x: minor for features or breaking changes, patch for fixes, chores and docs)
- [ ] Migrations are expand-first, so rolling back the code is safe

After merge: tag `vX.Y.Z` on the merge commit, wait for the Release workflow, then `deploy/cloudron/cloudron-deploy.sh vX.Y.Z` (see `AGENTS.md`).
