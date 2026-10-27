# Release packages and signing

Every tag `vX.Y.Z` (or `vX.Y.Z-beta.N`) runs the [Release workflow](../../.github/workflows/release.yml). It builds one zip that installs on shared hosting, Cloudron or a VPS, signs it, publishes it as a GitHub release and refreshes the update feeds.

## What a release contains

| Asset | Purpose |
| --- | --- |
| `npo-workdash-X.Y.Z.zip` | One `npo-workdash/` folder with `vendor/` and `public/build` included. No `.env`, tests, `node_modules` or dev tooling. |
| `npo-workdash-X.Y.Z.zip.sha256` | SHA-256 of the zip, in `sha256sum` format. |
| `npo-workdash-X.Y.Z.zip.sig` | Detached Ed25519 signature of the zip, base64. |
| `manifest.json` | Version, channel, release date, minimum PHP, MySQL and MariaDB versions, required extensions, minimum version to update from, migration list and a SHA-256 per file. Also inside the zip. |
| `feed-entry.json` | This release's entry in the update feeds. |

The workflow fails if `VERSION` does not match the tag, if `CHANGELOG.md` has no section for the version, or if the zip cannot be installed and serve `/login` on a plain `php:8.3-cli` container without Composer or Node.

## Update feeds

The feeds are static JSON on GitHub Pages, served from the `gh-pages` branch, and need no credentials:

- Stable: <https://apescic.github.io/NPO.WorkDash/feed/v1/stable.json> (stable releases only)
- Beta: <https://apescic.github.io/NPO.WorkDash/feed/v1/beta.json> (beta and stable releases)

The schema is [resources/schemas/update-feed.v1.json](../../resources/schemas/update-feed.v1.json). A breaking schema change gets a new `feed/v2/` path, so older installs keep reading `v1`.

Tags with a pre-release suffix (`-beta.N`) are published as GitHub pre-releases and appear only in the beta feed.

### Licence-gated downloads

An NPO.WorkDash app can also act as an update server at `/updates/feed/{channel}`. With `UPDATES_LICENCE_REQUIRED=true`, installations that do not send a valid `X-Licence-Key` header still see the releases but get no download links. The switch is off by default and stays off for APES and the hosted SaaS. Installations send `UPDATES_LICENCE_KEY` and their installation ID with every feed request.

## Verifying a download by hand

```bash
sha256sum --check npo-workdash-X.Y.Z.zip.sha256
php artisan release:verify npo-workdash-X.Y.Z.zip   # needs the .sha256 and .sig beside the zip
```

The installer and updater run the same check before extracting anything.

## The signing key

- **Private key**: the `RELEASE_SIGNING_KEY` GitHub Actions secret, a base64 libsodium Ed25519 secret key (64 bytes). It never goes into the repository, a release asset, an issue or chat. A backup is kept in the APES password manager.
- **Public key**: `updates.public_key` in [config/updates.php](../../config/updates.php), shipped in every release. `UPDATES_PUBLIC_KEY` overrides it only for CI test builds signed with a throwaway key.

### Rotating the key

Installs trust whichever public key their current release ships with, so rotation takes two releases:

1. Generate a new keypair, for example with Node: `crypto.generateKeyPairSync('ed25519')`, then join the 32-byte seed (`d`) and public key (`x`) from the JWK export and base64 encode them. Store the backup in the password manager.
2. Release N, signed with the **old** key, ships the **new** public key in `config/updates.php`.
3. Update the `RELEASE_SIGNING_KEY` secret with `gh secret set RELEASE_SIGNING_KEY` (paste from the password manager; never echo it in a terminal that is logged).
4. Release N+1 onwards is signed with the new key.

Installs older than release N must update to N before N+1.

If the private key leaks, remove the secret immediately and follow the same steps. Installs that cannot reach release N must reinstall from a manually verified zip.

### Losing the key

Without the private key, no existing install can verify a new release. Restore the key from the password manager backup. If the backup is gone too, publish a new key and reinstall each site manually.
