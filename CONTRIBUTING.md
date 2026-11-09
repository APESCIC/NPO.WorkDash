# Contributing to NPO.WorkDash

Please write in UK English. How work gets done, from issue to deploy, is in [AGENTS.md](AGENTS.md); local setup is in the [README](README.md).

## Milestone versioning

Milestones are named and shipped by version, never by GitHub's milestone number: branch `milestone/v0-3-0-saas-core`, PR title `[v0.3.0] Audit log (#98)`, `CHANGELOG.md` heading `## [0.3.0] - 2026-11-09`, tag `v0.3.0`.

- `composer milestone:next` shows the next milestone's version and branch name; `composer milestone:pr-title -- <issue>` gives the PR title.
- `composer milestone:check-title -- "<title>"` and `composer milestone:check-branch -- "<branch>"` tell you what is wrong. The `milestone-version-check` CI job runs them on every pull request.
- To check your branch name before each push, run `git config core.hooksPath .githooks` once.

Cursor agents follow [`.cursor/rules/milestone-versioning.mdc`](.cursor/rules/milestone-versioning.mdc) and the skills in `.cursor/skills/`. See [AGENTS.md](AGENTS.md#milestone-versioning).
