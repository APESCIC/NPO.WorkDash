<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;

test('demo user is not seeded outside the local environment', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::where('email', DemoSeeder::DEMO_EMAIL)->exists())->toBeFalse();
});

test('demo user is seeded in the local environment', function () {
    $this->app['env'] = 'local';

    $this->seed(DatabaseSeeder::class);

    expect(User::where('email', DemoSeeder::DEMO_EMAIL)->exists())->toBeTrue();
});
