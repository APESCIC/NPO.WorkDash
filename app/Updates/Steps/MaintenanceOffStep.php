<?php

namespace App\Updates\Steps;

use App\Updates\Engine\Step;
use App\Updates\Engine\StepContext;
use Illuminate\Support\Facades\Artisan;

final class MaintenanceOffStep implements Step
{
    public function name(): string
    {
        return 'maintenance_off';
    }

    public function run(StepContext $context): bool
    {
        Artisan::call('up');
        $context->log('Maintenance mode off.');

        return true;
    }
}
