<?php

namespace App\Updates\Backup;

use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

/**
 * Zip of .env and storage/app (without backups and update downloads), and
 * its restore.
 */
final class FileArchive
{
    /**
     * @param  list<string>  $exclude  absolute paths to leave out
     */
    public static function create(string $zipPath, string $envFile, string $storageApp, array $exclude): void
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot create {$zipPath}.");
        }

        if (is_file($envFile)) {
            $zip->addFile($envFile, '.env');
        }

        $exclude = array_map(fn (string $path) => rtrim(str_replace('\\', '/', $path), '/').'/', $exclude);

        if (is_dir($storageApp)) {
            foreach (File::allFiles($storageApp, true) as $file) {
                $path = str_replace('\\', '/', $file->getPathname());

                foreach ($exclude as $prefix) {
                    if (str_starts_with($path, $prefix)) {
                        continue 2;
                    }
                }

                $zip->addFile($file->getPathname(), 'storage/app/'.str_replace('\\', '/', $file->getRelativePathname()));
            }
        }

        $zip->close();
    }

    public static function restore(string $zipPath, string $envFile, string $storageApp): void
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException("Cannot open {$zipPath}.");
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            $target = $name === '.env' ? $envFile : (str_starts_with($name, 'storage/app/') ? $storageApp.'/'.substr($name, 12) : null);

            if ($target === null || str_contains($name, '..')) {
                continue;
            }

            File::ensureDirectoryExists(dirname($target));
            file_put_contents($target, (string) $zip->getFromIndex($i));
        }

        $zip->close();
    }
}
