<?php

namespace App\Updates\Steps;

use App\Support\HealthCheck;
use App\Updates\Engine\Step;
use App\Updates\Engine\StepContext;
use App\Updates\InvalidRelease;

final class HealthStep implements Step
{
    public function __construct(private readonly HealthCheck $health) {}

    public function name(): string
    {
        return 'health';
    }

    public function run(StepContext $context): bool
    {
        $result = $this->health->run();

        foreach ($result['checks'] as $name => $check) {
            $context->log(($check['ok'] ? 'OK ' : 'FAIL ')."{$name}: {$check['detail']}");
        }

        if (! $result['ok']) {
            throw new InvalidRelease('The health check failed after the update.');
        }

        return true;
    }
}
