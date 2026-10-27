<?php

use App\Install\InstallerService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
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

/**
 * Run a callback with environment variables temporarily set (string) or
 * removed (null), restoring the original values afterwards.
 *
 * @param  array<string, string|null>  $variables
 */
function withEnvironment(array $variables, callable $callback): mixed
{
    $original = [];

    foreach ($variables as $key => $value) {
        $original[$key] = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)];

        if ($value === null) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        } else {
            $_ENV[$key] = $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }
    }

    try {
        return $callback();
    } finally {
        foreach ($original as $key => [$env, $server, $getenv]) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);

            if ($env !== null) {
                $_ENV[$key] = $env;
            }

            if ($server !== null) {
                $_SERVER[$key] = $server;
            }

            if ($getenv !== false) {
                putenv("{$key}={$getenv}");
            }
        }
    }
}

/**
 * Connection settings for the testing database, as the installer expects them.
 *
 * @return array<string, mixed>
 */
function testDatabaseSettings(): array
{
    $mysql = config('database.connections.mysql');

    return [
        'host' => $mysql['host'],
        'port' => (int) $mysql['port'],
        'database' => $mysql['database'],
        'username' => $mysql['username'],
        'password' => (string) $mysql['password'],
    ];
}

/**
 * Walk the install wizard from the database step to the finish screen.
 *
 * @param  array<string, mixed>  $finish
 */
function completeWizard(array $finish = []): TestResponse
{
    app(InstallerService::class)->advance('database');

    test()->post('/install/database', [...testDatabaseSettings(), 'confirm_not_empty' => true])->assertRedirect('/install');
    test()->post('/install/migrate')->assertRedirect('/install');
    test()->post('/install/app', ['url' => 'https://workdash.example.test', 'timezone' => 'Europe/London'])->assertRedirect('/install');
    test()->post('/install/mail', ['mailer' => 'log'])->assertRedirect('/install');
    test()->post('/install/admin', [
        'name' => 'Example Admin',
        'email' => 'admin@example.test',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ])->assertRedirect('/install');
    test()->post('/install/workspace', ['name' => 'Example Animal Rescue'])->assertRedirect('/install');
    test()->post('/install/cron')->assertRedirect('/install');

    return test()->post('/install/finish', $finish)->assertOk();
}

/**
 * Point the app's .env at a throwaway copy of .env.example so installer
 * tests never touch the real one. Returns the directory.
 */
function useTemporaryEnvironmentFile(): string
{
    $dir = storage_path('framework/testing/env-'.uniqid());
    mkdir($dir, 0755, true);
    $example = (string) file_get_contents(base_path('.env.example'));
    file_put_contents($dir.'/.env', preg_replace('/^APP_KEY=.*$/m', 'APP_KEY='.config('app.key'), $example));
    app()->useEnvironmentPath($dir);

    return $dir;
}
