<?php

use App\Models\UpdateRun;
use App\Models\User;
use App\Updates\Engine\UpdateEngine;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\UpdateFixture;

beforeEach(function () {
    $this->fixture = new UpdateFixture;
    $this->admin = superAdminWithTwoFactor();
});

afterEach(fn () => $this->fixture->cleanUp());

test('only Super Admins can open the updater', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.updates.index'))->assertForbidden();
    $this->actingAs(User::factory()->create())->post(route('admin.updates.start'))->assertForbidden();
    $this->actingAs(User::factory()->create())->post(route('admin.updates.rollback'))->assertForbidden();
});

test('the updater shows the version, channel, available release and pre-flight results', function () {
    $this->actingAs($this->admin['user'])->get(route('admin.updates.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/updates')
            ->where('version', '1.0.0')
            ->where('channel', 'stable')
            ->where('release.version', '9.9.0')
            ->where('release.changelog', "### Added\n\n- Things.")
            ->where('preflight', fn ($items) => collect($items)->every(fn ($item) => $item['status'] === 'pass'))
            ->where('activeRun', null)
            ->where('requiresCode', true));

    expect(UpdateRun::query()->count())->toBe(0);
});

test('starting an update needs the password and a valid two-factor code', function () {
    $this->actingAs($this->admin['user'])
        ->post(route('admin.updates.start'), ['password' => 'wrong-password', 'code' => currentOtp($this->admin['secret'])])
        ->assertSessionHasErrors('password');

    $this->actingAs($this->admin['user'])
        ->post(route('admin.updates.start'), ['password' => 'password', 'code' => '000000'])
        ->assertSessionHasErrors('code');

    $this->actingAs($this->admin['user'])
        ->post(route('admin.updates.start'), ['password' => 'password'])
        ->assertSessionHasErrors('code');

    expect(UpdateRun::query()->count())->toBe(0);
});

test('a Super Admin starts the update and the page drives it to completion', function () {
    $this->actingAs($this->admin['user'])
        ->post(route('admin.updates.start'), ['password' => 'password', 'code' => currentOtp($this->admin['secret'])])
        ->assertRedirect(route('admin.updates.index'));

    $run = UpdateRun::query()->sole();
    expect($run->source)->toBe('web')->and($run->started_by)->toBe($this->admin['user']->id);

    $cookies = [];

    for ($i = 0; $i < 50 && ! $run->refresh()->isFinished(); $i++) {
        $response = $this->actingAs($this->admin['user'])->withUnencryptedCookies($cookies)->post(route('admin.updates.tick', $run));
        $response->assertRedirect(route('admin.updates.index'));

        if ($cookie = $response->getCookie('laravel_maintenance', false)) {
            $cookies['laravel_maintenance'] = $cookie->getValue();
        }
    }

    expect($run->status)->toBe(UpdateRun::SUCCEEDED)
        ->and($cookies)->toHaveKey('laravel_maintenance')
        ->and($this->fixture->currentRelease())->toBe("{$this->fixture->root}/releases/9.9.0");

    $this->actingAs($this->admin['user'])->get(route('admin.updates.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeRun', null)
            ->where('history.0.status', 'succeeded')
            ->where('history.0.startedBy', $this->admin['user']->name)
            ->where('canRollback', true));
});

test('the web updater and app:update produce the same result', function () {
    $web = app(UpdateEngine::class)->runToCompletion(app(UpdateEngine::class)->start($this->fixture->release, $this->admin['user'], 'web'));
    $webResult = [$web->status, $web->steps->pluck('status')->all(), basename($this->fixture->currentRelease()), scandir("{$this->fixture->root}/public/build")];
    $this->fixture->resetLayout();

    $this->artisan('app:update', ['--no-interaction' => true])->assertSuccessful();
    $cli = UpdateRun::query()->latest('id')->first();

    expect([$cli->status, $cli->steps->pluck('status')->all(), basename($this->fixture->currentRelease()), scandir("{$this->fixture->root}/public/build")])
        ->toBe($webResult);
});

test('update requests are CSRF-protected, rate limited and Super Admin only', function () {
    foreach (['admin.updates.start', 'admin.updates.tick', 'admin.updates.rollback'] as $name) {
        $middleware = Route::getRoutes()->getByName($name)->gatherMiddleware();

        expect($middleware)->toContain('web', 'auth', 'can:super-admin', 'throttle:updates');
    }
});
