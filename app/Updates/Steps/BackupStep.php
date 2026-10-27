<?php

namespace App\Updates\Steps;

use App\Updates\Backup\Backups;
use App\Updates\Backup\DatabaseDump;
use App\Updates\Backup\FileArchive;
use App\Updates\Engine\Step;
use App\Updates\Engine\StepContext;
use Illuminate\Support\Facades\File;

final class BackupStep implements Step
{
    public function name(): string
    {
        return 'backup';
    }

    public function run(StepContext $context): bool
    {
        $dir = $context->run->stateValue('backup_dir') ?? Backups::directoryFor($context->run->id);
        $context->run->mergeState(['backup_dir' => $dir]);
        File::ensureDirectoryExists($dir);

        $state = $context->step->state ?? [];

        if (! ($state['database_done'] ?? false)) {
            $dump = (new DatabaseDump((string) (config('updates.backup_connection') ?? config('database.default'))))
                ->run("{$dir}/database.sql", $state['database'] ?? [], $context->deadline, (int) config('updates.backup_rows_per_query'));

            $context->step->mergeState(['database' => $dump, 'database_done' => $dump['done']]);

            if (! $dump['done']) {
                $context->log("Database backup in progress: table {$dump['table']} of ".count($dump['tables']).'.');

                return false;
            }

            $context->log('Database backup written ('.count($dump['tables']).' tables).');

            if ($context->deadline->passed()) {
                return false;
            }
        }

        FileArchive::create("{$dir}/files.zip", app()->environmentFilePath(), storage_path('app'), [
            (string) config('updates.work_path'),
            (string) config('updates.backup_path'),
        ]);

        Backups::prune();
        $context->log("Backup complete in {$dir}.");

        return true;
    }
}
