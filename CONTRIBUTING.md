# Contributing

Please write in UK English. Local setup is in the [README](README.md): Laragon only, `composer setup` once, and `scripts\preview.ps1` before any work.

## How work ships

Each milestone is named by the version at the start of its GitHub milestone title (for example `v0.3.0 SaaS core`), never by GitHub's milestone number, and ships once: all its issues are committed on one `milestone/vX-Y-Z-<slug>` branch behind one draft pull request, which is merged once with a merge commit, tagged and released as `vX.Y.Z`, and deployed. A hotfix ships earlier only with Bambie's written approval on its issue.

The rule, with the names, the version fields, the flow and the hotfix route, is [`.cursor/rules/version-shipping.mdc`](.cursor/rules/version-shipping.mdc). Issue conventions and the checks to run are in [AGENTS.md](AGENTS.md).

- `composer milestone:next` shows the next version, its branch and its pull request and release titles.
- `composer milestone:check-title -- "<title>"` and `composer milestone:check-branch -- "<branch>"` tell you what is wrong. The `milestone-version-check` CI job runs them on every pull request.
- To check your branch name before each push, run `git config core.hooksPath .githooks` once.
