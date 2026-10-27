<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests run against the MySQL testing database configured in
| phpunit.xml and are wrapped in RefreshDatabase. Unit tests run without
| booting the framework.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * Act as a user inside a workspace.
 *
 * Workspaces do not exist yet (tenancy lands in milestone 1), so this
 * currently only authenticates the user. Call sites should use it now so
 * they gain workspace scoping automatically once tenancy is implemented.
 */
function actingAsWorkspaceUser(?User $user = null): TestCase
{
    return test()->actingAs($user ?? User::factory()->create());
}
