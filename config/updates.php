<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Release Signing Public Key
    |--------------------------------------------------------------------------
    |
    | Base64 Ed25519 public key that release zips must be signed with. The
    | private half lives only in the RELEASE_SIGNING_KEY GitHub Actions secret.
    | Overriding it is only for CI test builds signed with a throwaway key.
    | Rotation: docs/security/release-signing.md.
    |
    */

    'public_key' => env('UPDATES_PUBLIC_KEY', '6uB8TBS0hvk6U9ANqTClODv0afiPiPpoObsS5tTZFw0='),

    /*
    |--------------------------------------------------------------------------
    | Update Channel And Feed
    |--------------------------------------------------------------------------
    |
    | Installs follow the stable channel unless a Super Admin opts into beta.
    | The feed is static JSON published by the release workflow.
    |
    */

    'channel' => env('UPDATES_CHANNEL', 'stable'),

    'channels' => ['stable', 'beta'],

    'feed_url' => env('UPDATES_FEED_URL', 'https://apescic.github.io/NPO.WorkDash/feed/v1'),

    'download_base_url' => 'https://github.com/APESCIC/NPO.WorkDash/releases/download',

    /*
    |--------------------------------------------------------------------------
    | Release Requirements
    |--------------------------------------------------------------------------
    |
    | Written into every release manifest and checked by the requirements
    | checker and the updater's pre-flight.
    |
    */

    'requirements' => [
        'php' => '8.3.0',
        'mysql' => '8.0.0',
        'mariadb' => '10.6.0',
        'extensions' => [
            'ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'mbstring', 'openssl',
            'pcre', 'pdo', 'pdo_mysql', 'session', 'tokenizer', 'xml', 'zip',
        ],
    ],

    // Oldest installed version that can update straight to this release.
    'min_update_from' => '0.1.0',

    /*
    |--------------------------------------------------------------------------
    | Updater
    |--------------------------------------------------------------------------
    |
    | Updates run as a list of resumable steps. Each request (web) or loop
    | (CLI) works on one step for at most step_budget_seconds, so updates fit
    | hosts with a 30-second PHP limit.
    |
    */

    'step_budget_seconds' => 15,

    'download_chunk_bytes' => 4 * 1024 * 1024,

    'extract_batch' => 400,

    'backup_rows_per_query' => 1000,

    'keep_backups' => 3,

    // Not served over HTTP: storage lives outside the web root in every layout.
    'work_path' => storage_path('app/updates'),

    'backup_path' => storage_path('app/backups'),

    'log_path' => storage_path('logs/updates'),

    'lock_path' => storage_path('framework/updater.lock'),

    // Tables kept out of backups so a restore never rewinds the update history.
    'backup_exclude' => ['update_runs', 'update_steps'],

    // Database connection to back up and restore (null: the default connection).
    'backup_connection' => null,

    /*
    |--------------------------------------------------------------------------
    | Licence-Gated Downloads
    |--------------------------------------------------------------------------
    |
    | When required, the feed served by this app withholds download links from
    | installations without a valid licence key. Off for APES and the hosted
    | SaaS. The key is what this installation sends to an update server.
    |
    */

    'licence' => [
        'required' => (bool) env('UPDATES_LICENCE_REQUIRED', false),
        'key' => env('UPDATES_LICENCE_KEY'),
    ],

];
