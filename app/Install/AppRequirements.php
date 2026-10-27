<?php

namespace App\Install;

final class AppRequirements
{
    /**
     * The checker configured for this app, as used by the wizard and app:check.
     */
    public static function checker(bool $outbound = true): RequirementsChecker
    {
        $requirements = config('updates.requirements');

        return new RequirementsChecker([
            'base_path' => base_path(),
            'app_url' => (string) config('app.url'),
            'php' => $requirements['php'],
            'mysql' => $requirements['mysql'],
            'mariadb' => $requirements['mariadb'],
            'extensions' => $requirements['extensions'],
            'writable' => [
                'storage' => storage_path(),
                'bootstrap/cache' => base_path('bootstrap/cache'),
                '.env' => app()->environmentFilePath(),
            ],
            'outbound' => $outbound && config('install.check_outbound'),
        ]);
    }
}
