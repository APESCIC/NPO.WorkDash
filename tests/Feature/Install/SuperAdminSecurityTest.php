<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

beforeEach(fn () => $this->skipUnlessFortifyFeature(Features::twoFactorAuthentication()));

test('a Super Admin without two-factor authentication is sent to set it up', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('security.edit'));
});

test('the security page tells the Super Admin that two-factor authentication is required', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('twoFactorRequired', true));
});

test('a Super Admin with two-factor authentication can use the app', function () {
    $admin = User::factory()->superAdmin()->create();
    $admin->forceFill([
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->actingAs($admin)->get(route('dashboard'))->assertOk();
    $this->actingAs($admin)->get(route('admin.installation.show'))->assertOk();
});

test('ordinary users are not asked for two-factor authentication', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk();
});

test('ordinary users cannot open the installation page', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.installation.show'))->assertForbidden();
    $this->actingAs(User::factory()->create())->delete(route('admin.installation.cleanup'))->assertForbidden();
});

test('seeding outside local development creates no accounts', function () {
    app()->detectEnvironment(fn () => 'production');

    try {
        app(DatabaseSeeder::class)->setContainer(app())->__invoke();
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }

    expect(User::query()->count())->toBe(0);
});

test('no default account or password is defined in application code', function () {
    $code = collect(File::allFiles(app_path()))
        ->merge(File::allFiles(database_path('seeders')))
        ->merge(File::allFiles(database_path('migrations')));

    foreach ($code as $file) {
        expect($file->getContents())->not->toMatch("/(Hash::make|bcrypt)\(\s*['\"]/");
    }

    $seeding = collect(File::allFiles(database_path('seeders')))
        ->merge(File::allFiles(database_path('migrations')))
        ->reject(fn ($file) => $file->getFilename() === 'DemoSeeder.php');

    foreach ($seeding as $file) {
        expect($file->getContents())->not->toMatch("/'password'\s*=>\s*['\"]/");
    }
});
