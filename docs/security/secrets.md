# Configuration and secrets

NPO.WorkDash will hold credentials for Stripe, HMRC Charities Online, mail providers and other services, plus personal data protected by UK GDPR. This page explains where configuration lives in each environment and how secrets are handled.

**Rule one: never commit a secret.** `.env` is gitignored, and CI runs [gitleaks](https://github.com/gitleaks/gitleaks) on the full git history of every push and pull request. A detected secret fails the build.

## Where configuration lives

| Environment | Location | Database and mail credentials |
| --- | --- | --- |
| Local (Laragon) | `.env` in the repository root, copied from `.env.example` | `DB_*` and `MAIL_*` in `.env` (Laragon MySQL as `root`, Mailpit) |
| CI (GitHub Actions) | Job `env:` blocks in `.github/workflows/ci.yml`, plus GitHub Actions secrets for anything sensitive | MySQL service container, set in the workflow |
| Staging and production (Cloudron) | `/app/data/.env` on each Cloudron LAMP app | Read automatically from Cloudron's `CLOUDRON_MYSQL_*` and `CLOUDRON_MAIL_SMTP_*` variables |

`.env.example` lists every environment variable read in `config/`, grouped by area. Uncommented keys are the ones you normally set, and commented keys show optional overrides with their defaults. A test (`tests/Feature/EnvironmentConfigurationTest.php`) fails if a `config/` file reads a variable that `.env.example` does not document, so add new keys to `.env.example` in the same PR.

### Cloudron

The Cloudron LAMP app exposes addon credentials as environment variables, and the config files fall back to them when the matching Laravel key is absent:

| Laravel key | Cloudron fallback |
| --- | --- |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | `CLOUDRON_MYSQL_HOST`, `CLOUDRON_MYSQL_PORT`, `CLOUDRON_MYSQL_DATABASE`, `CLOUDRON_MYSQL_USERNAME`, `CLOUDRON_MYSQL_PASSWORD` |
| `MAIL_MAILER` | `smtp` when `CLOUDRON_MAIL_SMTP_SERVER` is set |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` | `CLOUDRON_MAIL_SMTP_SERVER`, `CLOUDRON_MAIL_SMTP_PORT`, `CLOUDRON_MAIL_SMTP_USERNAME`, `CLOUDRON_MAIL_SMTP_PASSWORD` |
| `MAIL_FROM_ADDRESS` | `CLOUDRON_MAIL_FROM` |
| `APP_URL` | `CLOUDRON_APP_ORIGIN` |

So on Cloudron, **leave the `DB_*` and `MAIL_*` credential keys out of `/app/data/.env`**. The app then uses the addon credentials, which Cloudron may change when an app is restored or cloned. An explicit `DB_*` or `MAIL_*` value always wins, which also means running the test suite on a server never silently targets the live database: `phpunit.xml` sets `DB_DATABASE` to the testing database.

Because production runs `php artisan config:cache`, re-run it (the deploy script does this) after changing `/app/data/.env`.

### GitHub Actions secrets

CI currently needs no secrets: the test database is a throwaway service container. When a workflow needs one (for example a Stripe test key for integration tests), add it under **Settings, Secrets and variables, Actions** and read it with `${{ secrets.NAME }}` in the job's `env:` block. Never echo secrets in workflow output. Pull requests from forks do not receive secrets.

## Third-party credentials stored per workspace

Some credentials belong to a charity rather than the platform, for example a workspace's HMRC Charities Online login or its own mail provider key. These are stored in the database, not in `.env`, and **must** use Laravel's encrypted casts:

```php
protected function casts(): array
{
    return [
        'hmrc_password' => 'encrypted',
        'mail_settings' => 'encrypted:array',
    ];
}
```

Conventions:

- Use `encrypted`, `encrypted:array`, `encrypted:collection` or `encrypted:object` for every secret column, and make the column `text`, because ciphertext is much longer than the plain value.
- Add the attribute to the model's `$hidden` array so it never appears in JSON or Inertia props.
- Never log decrypted values, and keep them out of audit-log diffs.
- Show only a masked hint in the UI (for example the last four characters), and require re-entry to change a value.

Encryption uses `APP_KEY`, so the next section matters for these columns too.

## APP_KEY and key rotation

`APP_KEY` encrypts session and cookie data, signed URLs and every `encrypted` cast. Each environment must have its own key (`php artisan key:generate`), and production's key must be backed up somewhere safe outside the server, because **a lost key makes encrypted columns unrecoverable**.

To rotate a key (for example after a suspected leak):

1. Put the current key in `APP_PREVIOUS_KEYS` (comma-separated if there are several), then set a new `APP_KEY`.
2. Run `php artisan config:cache`.
3. Laravel encrypts with the new key and can still decrypt with the previous keys, so existing encrypted columns keep working. Users with cookies encrypted under the old key may need to log in again.
4. Re-encrypt stored secrets under the new key (a one-off command that reads and re-saves each encrypted attribute), then remove the old key from `APP_PREVIOUS_KEYS`.

Rotating `APP_KEY` **without** adding the old key to `APP_PREVIOUS_KEYS` logs everyone out and makes every encrypted column undecryptable.

## If a secret is committed or leaked

1. **Revoke or rotate the secret at the provider first.** Removing it from git does not make it safe, because it may already have been cloned or cached.
2. Update the value in `/app/data/.env` on the affected Cloudron apps and re-run `php artisan config:cache`.
3. Remove the secret from the code in a normal PR. Rewriting history is only needed if the repository could become public, and needs agreement first because it breaks everyone's clones.
4. Record what happened in the Gitea issue for the incident.
