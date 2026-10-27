<?php

use App\Install\InstallerService;
use App\Models\Installation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->uninstall();
    $this->envDir = useTemporaryEnvironmentFile();
    @unlink(app(InstallerService::class)->progressPath());
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);
});

afterEach(function () {
    @unlink(app(InstallerService::class)->progressPath());
    File::deleteDirectory($this->envDir);
});

/**
 * @return array<string, mixed>
 */
function installOptions(): array
{
    $mysql = config('database.connections.mysql');

    return [
        '--no-interaction' => true,
        '--db-host' => $mysql['host'],
        '--db-port' => (string) $mysql['port'],
        '--db-database' => $mysql['database'],
        '--db-username' => $mysql['username'],
        '--db-password' => (string) $mysql['password'],
        '--url' => 'https://workdash.example.test',
        '--admin-name' => 'Example Admin',
        '--admin-email' => 'admin@example.test',
        '--admin-password' => 'correct-horse-battery-staple',
        '--workspace' => 'Example Animal Rescue',
        '--skip-requirements' => true,
    ];
}

/**
 * The install outcome, comparable between the wizard and the CLI.
 *
 * @return array<string, mixed>
 */
function installedState(): array
{
    return [
        'users' => User::query()->get()->map(fn (User $user) => [
            $user->name, $user->email, $user->is_super_admin, $user->email_verified_at !== null,
        ])->all(),
        'tenants' => Tenant::query()->get(['id', 'name', 'slug'])->toArray(),
        'installations' => Installation::query()->get()->map(fn (Installation $installation) => [
            $installation->installed_version, $installation->installed_by !== null,
        ])->all(),
        'locked' => is_file(config('install.lock_path')),
    ];
}

test('app:install --no-interaction installs with flags', function () {
    $this->artisan('app:install', installOptions())->assertSuccessful();

    expect(User::query()->sole())
        ->email->toBe('admin@example.test')
        ->is_super_admin->toBeTrue()
        ->and(Tenant::query()->sole()->id)->toBe(1)
        ->and(Installation::query()->count())->toBe(1)
        ->and(file_get_contents($this->envDir.'/.env'))->toContain('APP_URL=https://workdash.example.test');
});

test('app:install refuses to run when already installed', function () {
    $this->artisan('app:install', installOptions())->assertSuccessful();

    $this->artisan('app:install', [...installOptions(), '--admin-email' => 'second@example.test'])
        ->expectsOutputToContain('already installed')
        ->assertFailed();

    expect(User::query()->count())->toBe(1);
});

test('app:install rejects a weak Super Admin password', function () {
    $this->artisan('app:install', [...installOptions(), '--admin-password' => 'too-short'])->assertFailed();

    expect(Installation::query()->exists())->toBeFalse();
});

test('the wizard and app:install produce the same database state', function () {
    completeWizard();
    $wizard = installedState();

    User::query()->delete();
    Tenant::query()->delete();
    $this->uninstall();

    $this->artisan('app:install', installOptions())->assertSuccessful();

    expect(installedState())->toBe($wizard);
});

test('the installer reads the Cloudron MySQL addon variables with getenv', function () {
    $defaults = withEnvironment([
        'CLOUDRON_MYSQL_HOST' => 'mysql',
        'CLOUDRON_MYSQL_PORT' => '3306',
        'CLOUDRON_MYSQL_DATABASE' => 'cloudron_db',
        'CLOUDRON_MYSQL_USERNAME' => 'cloudron_user',
        'CLOUDRON_MYSQL_PASSWORD' => 'cloudron_secret',
    ], fn () => app(InstallerService::class)->databaseDefaults());

    expect($defaults)->toBe([
        'host' => 'mysql',
        'port' => '3306',
        'database' => 'cloudron_db',
        'username' => 'cloudron_user',
        'password' => 'cloudron_secret',
    ]);
});

test('a fresh host gets an .env with a new key and production defaults', function () {
    unlink($this->envDir.'/.env');

    expect(app(InstallerService::class)->prepareEnvironment())->toBeTrue();

    $env = (string) file_get_contents($this->envDir.'/.env');

    expect($env)->toMatch('/^APP_KEY=base64:[A-Za-z0-9+\/=]{44}$/m')
        ->toContain('APP_ENV=production')
        ->toContain('APP_DEBUG=false')
        ->toContain('MAIL_MAILER=log')
        ->and(app(InstallerService::class)->prepareEnvironment())->toBeFalse();
});
