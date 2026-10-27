<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SchedulerHeartbeat extends Command
{
    protected $signature = 'app:heartbeat';

    protected $description = 'Log a heartbeat so the scheduler can be seen running every minute';

    public function handle(): int
    {
        Log::info('Scheduler heartbeat', ['at' => now()->toIso8601String()]);

        return self::SUCCESS;
    }
}
