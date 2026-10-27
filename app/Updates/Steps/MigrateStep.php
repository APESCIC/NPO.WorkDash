<?php

namespace App\Updates\Steps;

use App\Install\InstallerService;
use App\Updates\Engine\Step;
use App\Updates\Engine\StepContext;

/**
 * One migration per tick, from the new release's own migrations folder.
 */
final class MigrateStep implements Step
{
    public function __construct(private readonly InstallerService $installer) {}

    public function name(): string
    {
        return 'migrate';
    }

    public function run(StepContext $context): bool
    {
        $path = $context->run->stateValue('release_path').'/database/migrations';
        $migration = $this->installer->runNextMigration(is_dir($path) ? $path : null);

        if ($migration === null) {
            $context->log('All migrations have run.');

            return true;
        }

        $context->run->mergeState(['migrated' => (int) $context->run->stateValue('migrated', 0) + 1]);
        $context->log("Ran {$migration}.");

        return false;
    }
}
