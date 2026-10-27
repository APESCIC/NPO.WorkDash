<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Proves a queue worker is running: dispatched by `app:queue-check` and logged when processed.
 */
class QueueHeartbeat implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $dispatchedAt) {}

    public function handle(): void
    {
        Log::info('Queue heartbeat processed', [
            'dispatched_at' => $this->dispatchedAt,
            'processed_at' => now()->toIso8601String(),
        ]);
    }
}
