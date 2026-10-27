<?php

namespace App\Updates\Engine;

use App\Models\UpdateRun;
use App\Models\UpdateStep;
use App\Updates\Deadline;
use App\Updates\Layout\ReleaseLayoutDriver;

/**
 * What a step works with during one tick.
 */
final class StepContext
{
    public function __construct(
        public readonly UpdateRun $run,
        public readonly UpdateStep $step,
        public readonly ReleaseLayoutDriver $layout,
        public readonly Deadline $deadline,
    ) {}

    public function log(string $message): void
    {
        UpdateLog::write($this->run, "[{$this->step->name}] {$message}");
    }

    /**
     * The update's own working folder for downloads and staging.
     */
    public function workPath(string $suffix = ''): string
    {
        $path = rtrim(str_replace('\\', '/', (string) config('updates.work_path')), '/')."/run-{$this->run->id}";

        return $suffix === '' ? $path : "{$path}/{$suffix}";
    }
}
