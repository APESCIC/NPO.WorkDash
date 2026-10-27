<?php

namespace App\Updates;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ReleaseManifest
{
    public const FILE = 'manifest.json';

    /**
     * Describe the extracted release at $root: requirements, migrations and a
     * SHA-256 for every file.
     *
     * @return array<string, mixed>
     */
    public static function build(string $root, string $version, string $releasedAt): array
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relative = substr(str_replace('\\', '/', $file->getPathname()), strlen($root) + 1);

            if ($relative !== self::FILE) {
                $files[$relative] = (string) hash_file('sha256', $file->getPathname());
            }
        }

        ksort($files, SORT_STRING);

        $migrations = array_values(array_map(
            fn (string $path) => basename($path, '.php'),
            array_filter(array_keys($files), fn (string $path) => str_starts_with($path, 'database/migrations/') && str_ends_with($path, '.php')),
        ));

        return [
            'schema' => 1,
            'version' => $version,
            'channel' => Version::channel($version),
            'released_at' => $releasedAt,
            'requirements' => config('updates.requirements'),
            'min_update_from' => config('updates.min_update_from'),
            'migrations' => $migrations,
            'files' => $files,
        ];
    }

    /**
     * Relative paths whose contents differ from the manifest, or that are missing.
     *
     * @param  array<string, mixed>  $manifest
     * @return list<string>
     */
    public static function mismatches(string $root, array $manifest): array
    {
        $root = rtrim($root, '/\\');
        $bad = [];

        foreach ($manifest['files'] ?? [] as $path => $hash) {
            $full = $root.'/'.$path;

            if (! is_file($full) || ! hash_equals($hash, (string) hash_file('sha256', $full))) {
                $bad[] = $path;
            }
        }

        return $bad;
    }
}
