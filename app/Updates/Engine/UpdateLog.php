<?php

namespace App\Updates\Engine;

use App\Models\UpdateRun;
use Illuminate\Support\Facades\File;

/**
 * One log file per update run in storage/logs/updates/.
 */
final class UpdateLog
{
    public static function path(UpdateRun $run): string
    {
        return rtrim((string) config('updates.log_path'), '/\\')."/update-{$run->id}.log";
    }

    public static function write(UpdateRun $run, string $message): void
    {
        $path = self::path($run);
        File::ensureDirectoryExists(dirname($path));
        file_put_contents($path, '['.now()->toIso8601String()."] {$message}\n", FILE_APPEND);
    }

    public static function read(UpdateRun $run): string
    {
        return is_file(self::path($run)) ? (string) file_get_contents(self::path($run)) : '';
    }
}
