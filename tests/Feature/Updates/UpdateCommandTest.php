<?php

use App\Models\UpdateRun;
use App\Updates\Engine\UpdateEngine;
use Tests\Support\UpdateFixture;

beforeEach(fn () => $this->fixture = new UpdateFixture);

afterEach(fn () => $this->fixture->cleanUp());

test('app:update runs every step and exits successfully', function () {
    $this->artisan('app:update', ['--no-interaction' => true])
        ->expectsOutputToContain('Update available: 1.0.0 to 9.9.0')
        ->expectsOutputToContain('Updated to 9.9.0')
        ->assertSuccessful();

    expect(UpdateRun::query()->sole())
        ->status->toBe(UpdateRun::SUCCEEDED)
        ->source->toBe('cli')
        ->and($this->fixture->currentRelease())->toEndWith('/releases/9.9.0');
});

test('app:update exits non-zero when the update fails', function () {
    $this->fixture->buildZip(tamper: true);

    $this->artisan('app:update', ['--no-interaction' => true])->assertFailed();

    expect(UpdateRun::query()->sole()->status)->toBe(UpdateRun::FAILED)
        ->and($this->fixture->currentRelease())->toEndWith('/releases/1.0.0');
});

test('app:update --check only reports', function () {
    $this->artisan('app:update', ['--check' => true])
        ->expectsOutputToContain('Update available')
        ->assertSuccessful();

    expect(UpdateRun::query()->count())->toBe(0);
});

test('app:update reports when already up to date', function () {
    config(['app.version' => '9.9.0']);

    $this->artisan('app:update')->expectsOutputToContain('is up to date')->assertSuccessful();
});

test('app:update resumes an interrupted update', function () {
    $run = app(UpdateEngine::class)->start($this->fixture->release, null, 'web');

    $this->artisan('app:update')->expectsOutputToContain('Resuming the update to 9.9.0')->assertSuccessful();

    expect($run->refresh()->status)->toBe(UpdateRun::SUCCEEDED);
});

test('app:update asks a long-running queue worker to restart', function () {
    config(['queue.runner' => 'worker']);

    $this->artisan('app:update')->assertSuccessful();

    expect(cache()->get('illuminate:queue:restart'))->not->toBeNull();
});
