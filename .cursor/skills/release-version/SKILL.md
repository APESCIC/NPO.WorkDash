---
name: release-version
description: Ship a completed version - set VERSION and the CHANGELOG.md section once, mark the milestone PR ready, get CI green, ask the ship-gate question, then merge once (merge commit), push the vX.Y.Z tag (release.yml builds the signed zip and publishes the Release), deploy that tag with cloudron-deploy.sh and close out. Use when every issue in the milestone is committed, or when the user asks to merge, release, tag or deploy the milestone.
---

# Release a version

Follows `.cursor/rules/version-shipping.mdc`, the `ship-gate` skill and `docs/deploy/cloudron.md`.

## Steps

1. **Sync.** Run `scripts\preview.ps1` (it merges `origin/main` into the milestone branch).
2. **Set the version once.** `composer milestone:bump -- vX.Y.Z` (it refuses a version that is not the next milestone's). Move the `## [Unreleased]` entries into `## [X.Y.Z] - YYYY-MM-DD` in `CHANGELOG.md` and update the compare links.
3. **Last commit.** `chore: release vX.Y.Z`. Run `composer ci:check`, then push.
4. **Ready.** `gh pr ready <pr>`, then `gh pr checks <pr> --watch`. Fix failures on the same branch.
5. **Check the names.** `composer milestone:check-release -- vX.Y.Z "<release title>" --changelog "## [X.Y.Z] - YYYY-MM-DD"`, using the "Release title" from `composer milestone:next`.
6. **Ship gate.** Summarise the milestone, PR, checks, merge method, tag and deploy target, then ask with exactly: a. Merge, b. Release, c. Deploy, d. Merge, release and deploy, e. Stop here. Run only what was chosen; after each single step ask again with what remains.
7. **Merge.** `gh pr merge <pr> --merge --delete-branch`.
8. **Release.** Tag the merge commit and push the tag; `release.yml` builds and signs the zip, refreshes the update feeds and publishes the Release:

   ```powershell
   git fetch origin; git tag -a vX.Y.Z -m "vX.Y.Z" <merge-sha>; git push origin vX.Y.Z
   gh run watch (gh run list --workflow release.yml --limit 1 --json databaseId --jq '.[0].databaseId') --exit-status
   ```

9. **Deploy.** Check no other session is about to deploy (`git worktree list`, `gh pr list`), then `& "C:\Program Files\Git\bin\bash.exe" deploy/cloudron/cloudron-deploy.sh vX.Y.Z`. It must end with `/up` returning 200 and the live `REVISION` matching the tag; its output includes the seconds each stage took.
10. **Close out** the issues, the Epic and the milestone, then merge `origin/main` into `dependencies`. Post the completion message with the PR, merge commit, issues, release and deploy evidence.

## Never

- Squash or rebase the milestone PR, tag anything but the merge commit, or deploy `main` or a branch.
- Rewrite a past tag, Release or `CHANGELOG.md` section.
- Report a step without its command output.
