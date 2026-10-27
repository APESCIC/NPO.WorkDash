<?php

namespace App\Updates\Steps;

use App\Updates\Engine\Step;
use App\Updates\Engine\StepContext;
use App\Updates\InvalidRelease;
use App\Updates\Preflight;

final class PreflightStep implements Step
{
    public function name(): string
    {
        return 'preflight';
    }

    public function run(StepContext $context): bool
    {
        $items = Preflight::check($context->run->release, $context->layout);

        foreach ($items as $item) {
            $context->log(strtoupper($item['status'])." {$item['label']}: {$item['detail']}");
        }

        if (! Preflight::passes($items)) {
            $failed = collect($items)->where('status', 'fail')->pluck('detail')->implode(' ');

            throw new InvalidRelease("Pre-flight checks failed, nothing was changed. {$failed}");
        }

        $context->run->mergeState(['previous_release' => $context->layout->current()]);

        return true;
    }
}
