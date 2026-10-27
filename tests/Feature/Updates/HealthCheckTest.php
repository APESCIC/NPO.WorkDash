<?php

use App\Models\Installation;
use App\Support\Heartbeat;

test('/up returns 200 with every check when the app is healthy', function () {
    Heartbeat::record();

    $this->getJson('/up')
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('version', config('app.version'))
        ->assertJsonPath('checks.database.ok', true)
        ->assertJsonPath('checks.cache.ok', true)
        ->assertJsonPath('checks.storage.ok', true)
        ->assertJsonPath('checks.scheduler.ok', true)
        ->assertJsonPath('checks.version.ok', true);
});

test('/up returns 503 when the scheduler has stopped', function () {
    Installation::query()->update(['installed_at' => now()->subHour()]);
    cache()->put(Heartbeat::KEY, now()->subMinutes(30)->getTimestamp());

    $this->getJson('/up')
        ->assertStatus(503)
        ->assertJsonPath('status', 'failing')
        ->assertJsonPath('checks.scheduler.ok', false);
});

test('/up returns 503 when the version does not match the loaded configuration', function () {
    Heartbeat::record();
    config(['app.version' => '0.0.1']);

    $this->getJson('/up')->assertStatus(503)->assertJsonPath('checks.version.ok', false);
});

test('/up is not cached', function () {
    Heartbeat::record();

    expect($this->getJson('/up')->headers->get('Cache-Control'))->toContain('no-store');
});
