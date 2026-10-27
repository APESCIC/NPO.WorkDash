<?php

namespace App\Updates\Events;

use App\Models\UpdateRun;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An update run ended: succeeded, failed or rolled back.
 */
final class UpdateFinished
{
    use Dispatchable;

    public function __construct(public readonly UpdateRun $run) {}
}
