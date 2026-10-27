<?php

namespace App\Updates\Steps;

use App\Updates\Engine\Step;
use App\Updates\Engine\StepContext;

final class SwapStep implements Step
{
    public function name(): string
    {
        return 'swap';
    }

    public function run(StepContext $context): bool
    {
        $staged = (string) $context->run->stateValue('staged_public');

        if (is_dir($staged)) {
            $context->layout->publishAssets($staged);
        }

        $context->layout->activate((string) $context->run->stateValue('release_path'));
        $context->run->mergeState(['swapped' => true]);
        $context->log('Switched to '.$context->run->stateValue('release_path').'.');

        return true;
    }
}
