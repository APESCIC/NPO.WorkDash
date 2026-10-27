<?php

namespace App\Updates\Backup;

use Illuminate\Support\Facades\File;

/**
 * Pre-update backups: one folder each under updates.backup_path, newest kept.
 */
final class Backups
{
    public static function path(): string
    {
        return rtrim(str_replace('\\', '/', (string) config('updates.backup_path')), '/');
    }

    public static function directoryFor(int $runId): string
    {
        return self::path().'/'.now()->format('Ymd-His')."-run-{$runId}";
    }

    /**
     * @return list<string> backup folders, newest first
     */
    public static function all(): array
    {
        $folders = glob(self::path().'/*', GLOB_ONLYDIR) ?: [];
        rsort($folders, SORT_STRING);

        return $folders;
    }

    /**
     * Keep the newest updates.keep_backups backups and delete the rest.
     */
    public static function prune(): void
    {
        foreach (array_slice(self::all(), (int) config('updates.keep_backups')) as $folder) {
            File::deleteDirectory($folder);
        }
    }
}
