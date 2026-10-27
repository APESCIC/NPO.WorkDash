<?php

namespace App\Updates\Engine;

/**
 * One idempotent update step. run() may be called again after a crash or a
 * time-out, and must carry on from what $context->step->state records.
 */
interface Step
{
    public function name(): string;

    /**
     * Do work until finished or the deadline passes. Returns true when done.
     */
    public function run(StepContext $context): bool;
}
