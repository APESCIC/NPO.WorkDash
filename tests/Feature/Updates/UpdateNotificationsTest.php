<?php

use App\Models\UpdateRun;
use App\Models\User;
use App\Notifications\UpdateAvailable;
use App\Notifications\UpdateFinishedNotification;
use App\Updates\Engine\UpdateEngine;
use App\Updates\Engine\UpdateLog;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;
use Tests\Support\UpdateFixture;

beforeEach(function () {
    $this->fixture = new UpdateFixture;
    $this->admin = User::factory()->superAdmin()->create();
    $this->member = User::factory()->create();
    Notification::fake();
});

afterEach(fn () => $this->fixture->cleanUp());

test('Super Admins get one email when a newer release is available', function () {
    $this->artisan('app:check-updates')->expectsOutputToContain('9.9.0 is available')->assertSuccessful();
    $this->artisan('app:check-updates')->expectsOutputToContain('already told')->assertSuccessful();

    Notification::assertSentToTimes($this->admin, UpdateAvailable::class, 1);
    Notification::assertNotSentTo($this->member, UpdateAvailable::class);
});

test('no email when the site is up to date', function () {
    config(['app.version' => '9.9.0']);

    $this->artisan('app:check-updates')->assertSuccessful();

    Notification::assertNothingSent();
});

test('beta releases are not announced on the stable channel', function () {
    $this->fixture->version = '9.9.0-beta.1';
    $this->fixture->buildZip();
    $this->fixture->release['channel'] = 'beta';

    $this->artisan('app:check-updates')->expectsOutputToContain('Up to date')->assertSuccessful();

    Notification::assertNothingSent();
});

test('the availability check is scheduled daily and never installs anything', function () {
    $events = collect(app(Schedule::class)->events());
    $check = $events->first(fn (Event $event) => str_contains((string) $event->command, 'app:check-updates'));

    expect($check?->expression)->toBe('0 6 * * *')
        ->and($events->filter(fn (Event $event) => str_contains((string) $event->command, 'app:update ')))->toBeEmpty();
});

test('a successful update emails Super Admins with the versions and a log link', function () {
    $run = app(UpdateEngine::class)->runToCompletion(app(UpdateEngine::class)->start($this->fixture->release, $this->admin, 'web'));

    Notification::assertSentTo($this->admin, UpdateFinishedNotification::class, function (UpdateFinishedNotification $notification) use ($run) {
        $mail = $notification->toMail($this->admin);

        return $notification->run->is($run)
            && str_contains($mail->subject, 'update to 9.9.0 succeeded')
            && str_contains(implode(' ', $mail->introLines), 'from 1.0.0 to 9.9.0')
            && $mail->actionUrl === route('admin.updates.log', $run);
    });
    Notification::assertNotSentTo($this->member, UpdateFinishedNotification::class);
});

test('a failed update emails Super Admins the reason', function () {
    $this->fixture->healthy(false);

    $run = app(UpdateEngine::class)->runToCompletion(app(UpdateEngine::class)->start($this->fixture->release, $this->admin, 'web'));

    Notification::assertSentTo($this->admin, UpdateFinishedNotification::class, function (UpdateFinishedNotification $notification) {
        $mail = $notification->toMail($this->admin);

        return str_contains($mail->subject, 'failed and was rolled back')
            && $mail->level === 'error'
            && str_contains(implode(' ', $mail->introLines), 'health check failed');
    });
    expect($run->status)->toBe(UpdateRun::ROLLED_BACK);
});

test('the update audit records who, from and to version, duration and result', function () {
    $run = app(UpdateEngine::class)->runToCompletion(app(UpdateEngine::class)->start($this->fixture->release, $this->admin, 'web'));

    expect($run->fresh())
        ->started_by->toBe($this->admin->id)
        ->from_version->toBe('1.0.0')
        ->to_version->toBe('9.9.0')
        ->status->toBe(UpdateRun::SUCCEEDED)
        ->and($run->durationSeconds())->toBeInt()
        ->and(UpdateLog::path($run))->toEndWith("logs/updates/update-{$run->id}.log")
        ->and(UpdateLog::read($run))->toContain("by {$this->admin->email}");
});

test('only Super Admins can read update logs', function () {
    $run = app(UpdateEngine::class)->start($this->fixture->release, $this->admin, 'web');
    $admin = superAdminWithTwoFactor()['user'];

    $this->actingAs($this->member)->get(route('admin.updates.log', $run))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.updates.log', $run))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->assertSee('Update from 1.0.0 to 9.9.0 started');
});

test('app:update --step does one step per run, like a web request', function () {
    $this->artisan('app:update', ['--step' => true])->expectsOutputToContain('status=running next=backup')->assertSuccessful();

    expect(UpdateRun::query()->sole()->steps()->where('status', 'done')->count())->toBe(1);

    for ($i = 0; $i < 30 && UpdateRun::query()->sole()->status !== UpdateRun::SUCCEEDED; $i++) {
        $this->artisan('app:update', ['--step' => true])->assertSuccessful();
    }

    expect(UpdateRun::query()->sole()->status)->toBe(UpdateRun::SUCCEEDED);
});
