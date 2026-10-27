<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

const SEMVER_PATTERN = '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(-[0-9A-Za-z-]+(\.[0-9A-Za-z-]+)*)?(\+[0-9A-Za-z-]+(\.[0-9A-Za-z-]+)*)?$/';

test('the VERSION file holds a valid semantic version', function () {
    $version = trim((string) file_get_contents(base_path('VERSION')));

    expect($version)->toMatch(SEMVER_PATTERN);
});

test('the app version is read from the VERSION file', function () {
    expect(config('app.version'))->toBe(trim((string) file_get_contents(base_path('VERSION'))));
});

test('the app version is shared with every Inertia page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('version', config('app.version')));
});
