<?php

namespace App\Console\Commands;

use App\Updates\Engine\UpdateEngine;
use Illuminate\Console\Command;
use RuntimeException;

class RollbackApp extends Command
{
    protected $signature = 'app:rollback';

    protected $description = 'Undo the last update: switch back to the previous release and restore the database if migrations ran';

    public function handle(UpdateEngine $engine): int
    {
        try {
            $run = $engine->rollbackLatest(null, 'cli');
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Rolled back from {$run->to_version} to {$run->from_version}.");

        return self::SUCCESS;
    }
}
