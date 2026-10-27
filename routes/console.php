<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:heartbeat')->everyMinute()->withoutOverlapping();

if (config('queue.runner') === 'cron') {
    Schedule::command('queue:work', ['--stop-when-empty', '--max-time=50', '--tries=3'])
        ->everyMinute()
        ->withoutOverlapping();
}
