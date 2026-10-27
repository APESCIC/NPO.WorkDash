<?php

namespace App\Updates\Steps;

use App\Updates\Engine\Step;
use App\Updates\Engine\StepContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * Keep the new and the previous release (for rollback), remove older ones
 * and the download, and restart a long-running queue worker.
 */
final class CleanupStep implements Step
{
    public function name(): string
    {
        return 'cleanup';
    }

    public function run(StepContext $context): bool
    {
        $keep = array_map(
            fn ($path) => rtrim(str_replace('\\', '/', (string) realpath((string) $path)), '/'),
            [$context->run->stateValue('release_path'), $context->run->stateValue('previous_release')],
        );

        foreach ($context->layout->releases() as $release) {
            if (! in_array(rtrim(str_replace('\\', '/', (string) realpath($release)), '/'), $keep, true)) {
                File::deleteDirectory($release);
                $context->log("Removed old release {$release}.");
            }
        }

        File::deleteDirectory($context->workPath());

        if (config('queue.runner') === 'worker') {
            Artisan::call('queue:restart');
            $context->log('Asked the queue worker to restart on the new code.');
        }

        return true;
    }
}
