<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Installed Lock File
    |--------------------------------------------------------------------------
    |
    | Written when installation finishes, alongside the installations table
    | record. Either one marks the app as installed, so deleting the lock file
    | alone never re-opens the installer.
    |
    */

    'lock_path' => storage_path('installed.lock'),

    // Whether the requirements check tries an outbound HTTPS connection.
    'check_outbound' => true,

    /*
    |--------------------------------------------------------------------------
    | Web Wizard
    |--------------------------------------------------------------------------
    |
    | Off on Cloudron, where apps are reachable as soon as they are deployed
    | and are installed from the terminal with `php artisan app:install`.
    |
    */

    'web_wizard' => (bool) env('INSTALL_WEB_WIZARD', ! getenv('CLOUDRON')),

    /*
    |--------------------------------------------------------------------------
    | Guides
    |--------------------------------------------------------------------------
    |
    | The wizard links to these step-by-step guides.
    |
    */

    'guides_url' => 'https://github.com/APESCIC/NPO.WorkDash/blob/main/docs/install',

];
