<?php

namespace App\Updates\Steps;

use App\Updates\Engine\Step;
use App\Updates\Engine\StepContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

final class MaintenanceOnStep implements Step
{
    public function name(): string
    {
        return 'maintenance_on';
    }

    public function run(StepContext $context): bool
    {
        // The secret lets the Super Admin's browser keep driving the update.
        $secret = $context->run->stateValue('maintenance_secret') ?? Str::random(40);
        $context->run->mergeState(['maintenance_secret' => $secret]);

        Artisan::call('down', ['--secret' => $secret, '--retry' => 15]);
        $context->log('Maintenance mode on.');

        return true;
    }
}
