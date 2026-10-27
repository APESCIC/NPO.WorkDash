<?php

namespace App\Install;

use Illuminate\Foundation\Application;

/**
 * The release layout used by the zip and the updater:
 *
 *   npo-workdash/current.php        returns the live release path (the only file an update rewrites)
 *   npo-workdash/artisan            runs the live release's artisan (for cron)
 *   npo-workdash/public/            starter web root (or copied into public_html)
 *   npo-workdash/releases/<version> app code
 *   npo-workdash/shared/            .env and storage, shared by all releases
 */
final class ReleaseLayout
{
    /**
     * Point .env and storage at shared/ when running from releases/<version>.
     */
    public static function apply(Application $app): void
    {
        $root = self::root($app->basePath());

        if ($root !== null) {
            $app->useEnvironmentPath($root.'/shared');
            $app->useStoragePath($root.'/shared/storage');
        }
    }

    /**
     * The layout root (the folder holding releases/ and shared/), or null
     * when the app is not running from the release layout.
     */
    public static function root(string $basePath): ?string
    {
        $parent = dirname($basePath);
        $root = dirname($parent);

        return basename($parent) === 'releases' && is_dir($root.'/shared') ? $root : null;
    }

    /**
     * The artisan path for the cron line: the stable proxy in the layout
     * root when present, so cron keeps working across updates.
     */
    public static function cronArtisan(string $basePath): string
    {
        $root = self::root($basePath);

        return $root !== null && is_file($root.'/artisan') ? $root.'/artisan' : $basePath.'/artisan';
    }
}
