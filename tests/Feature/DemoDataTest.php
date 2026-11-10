<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\Heartbeat;
use Database\Seeders\DemoSeeder;

test('the demo seeder refuses to run outside the local environment', function () {
    $this->seed(DemoSeeder::class);
})->throws(RuntimeException::class, 'APP_ENV=local');

test('the demo commands refuse in production and change nothing', function () {
    $this->app['env'] = 'production';

    $this->artisan('demo:seed')->expectsOutputToContain('APP_ENV=local')->assertExitCode(1);
    $this->artisan('demo:reset')->expectsOutputToContain('APP_ENV=local')->assertExitCode(1);

    expect(User::query()->where('email', DemoSeeder::DEMO_EMAIL)->exists())->toBeFalse();
});

test('demo:seed loads fictional tenants and example.test users locally', function () {
    $this->app['env'] = 'local';

    $this->artisan('demo:seed')->assertExitCode(0);

    expect(Tenant::query()->where('name', 'like', '%(fake)')->count())->toBe(3)
        ->and(User::query()->count())->toBe(13)
        ->and(User::pluck('email'))->each->toEndWith('@example.test');
});

test('/up adds the PHP handler and revision on the local preview only', function () {
    Heartbeat::record();

    expect($this->getJson('/up')->json())->not->toHaveKey('local');

    $this->app['env'] = 'local';
    $local = $this->getJson('/up')->json('local');

    expect($local['php'])->toBe(PHP_VERSION)
        ->and($local['php_sapi'])->toBe(PHP_SAPI)
        ->and($local)->toHaveKey('git_revision');
});
