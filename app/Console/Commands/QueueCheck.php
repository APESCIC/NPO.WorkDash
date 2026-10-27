<?php

namespace App\Console\Commands;

use App\Jobs\QueueHeartbeat;
use Illuminate\Console\Command;

class QueueCheck extends Command
{
    protected $signature = 'app:queue-check';

    protected $description = 'Dispatch a heartbeat job to prove the queue worker is processing jobs';

    public function handle(): int
    {
        QueueHeartbeat::dispatch(now()->toIso8601String());

        $this->info('Queue heartbeat dispatched. Look for "Queue heartbeat processed" in the logs.');

        return self::SUCCESS;
    }
}
