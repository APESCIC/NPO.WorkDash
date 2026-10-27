<?php

use App\Models\User;

test('the application uses UK locale and London timezone', function () {
    expect(config('app.locale'))->toBe('en_GB')
        ->and(config('app.timezone'))->toBe('Europe/London');
});

test('acting as a workspace user authenticates them', function () {
    $user = User::factory()->create();

    actingAsWorkspaceUser($user)->get(route('dashboard'))->assertOk();

    $this->assertAuthenticatedAs($user);
});
