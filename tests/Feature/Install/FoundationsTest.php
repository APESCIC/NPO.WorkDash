<?php

use App\Install\InstallState;
use App\Models\Installation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('only Super Admins pass the super-admin gate', function () {
    expect(Gate::forUser(User::factory()->superAdmin()->create())->allows('super-admin'))->toBeTrue()
        ->and(Gate::forUser(User::factory()->create())->allows('super-admin'))->toBeFalse();
});

test('is_super_admin cannot be mass assigned', function () {
    $user = new User(['name' => 'Someone', 'email' => 'someone@example.test', 'is_super_admin' => true]);

    expect($user->is_super_admin)->toBeNull();
});

test('app:super-admin promotes an existing user', function () {
    $user = User::factory()->create(['email' => 'admin@example.test']);

    $this->artisan('app:super-admin', ['email' => 'ADMIN@example.test'])->assertSuccessful();

    expect($user->fresh()->is_super_admin)->toBeTrue();
});

test('app:super-admin fails for an unknown email', function () {
    $this->artisan('app:super-admin', ['email' => 'nobody@example.test'])->assertFailed();
});

function runAdoptionMigration(): void
{
    (require database_path('migrations/2026_10_27_110300_adopt_existing_installation.php'))->up();
}

test('a database that already has users is adopted as installed with workspace 1', function () {
    $this->uninstall();
    User::factory()->create();

    runAdoptionMigration();

    expect(Installation::query()->count())->toBe(1)
        ->and(Installation::query()->first()->installed_version)->toBe(config('app.version'))
        ->and(Tenant::query()->find(1))->not->toBeNull()
        ->and(is_file(config('install.lock_path')))->toBeTrue();
});

test('the adopted production database is not sent to the installer', function () {
    $this->uninstall();
    User::factory()->create();
    runAdoptionMigration();
    app(InstallState::class)->forget();

    $this->get('/')->assertOk();
    $this->get('/install')->assertNotFound();
});

test('a blank database is not adopted', function () {
    $this->uninstall();

    runAdoptionMigration();

    expect(Installation::query()->exists())->toBeFalse()
        ->and(Tenant::query()->exists())->toBeFalse();
});
