<?php

use App\Install\PhpBinary;
use App\Jobs\QueueHeartbeat;
use App\Models\Installation;
use App\Models\User;
use App\Support\Heartbeat;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule as ScheduleFacade;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return list<Event>
 */
function scheduledEventsFor(string $runner): array
{
    config(['queue.runner' => $runner]);
    ScheduleFacade::swap($schedule = new Schedule);
    require base_path('routes/console.php');

    return $schedule->events();
}

test('the cron line uses the detected PHP CLI binary', function () {
    $php = PhpBinary::detect('/opt/cpanel/ea-php83/root/usr/sbin/php-fpm', [PHP_BINARY]);

    expect($php)->toBe(PHP_BINARY)
        ->and(PhpBinary::cronLine($php, '/home/charity/npo-workdash/artisan'))
        ->toBe('* * * * * '.PHP_BINARY.' /home/charity/npo-workdash/artisan schedule:run >> /dev/null 2>&1');
});

test('a CLI PHP binary is used as it is', function () {
    expect(PhpBinary::detect(PHP_BINARY))->toBe(PHP_BINARY);
});

test('with the cron runner the scheduler drains the queue every minute', function () {
    $queue = collect(scheduledEventsFor('cron'))->first(fn (Event $event) => str_contains((string) $event->command, 'queue:work'));

    expect($queue)->not->toBeNull()
        ->and($queue->expression)->toBe('* * * * *')
        ->and($queue->command)->toContain('--stop-when-empty')
        ->toContain('--max-time=50');
});

test('with a supervised worker the scheduler does not start a second one', function () {
    $commands = collect(scheduledEventsFor('worker'))->map(fn (Event $event) => (string) $event->command);

    expect($commands->filter(fn (string $command) => str_contains($command, 'queue:work')))->toBeEmpty()
        ->and($commands->filter(fn (string $command) => str_contains($command, 'app:heartbeat')))->toHaveCount(1);
});

test('Cloudron defaults to the supervised worker and other hosts to cron', function () {
    $cloudron = withEnvironment(['QUEUE_RUNNER' => null, 'CLOUDRON' => '1'], fn () => (require config_path('queue.php'))['runner']);
    $shared = withEnvironment(['QUEUE_RUNNER' => null, 'CLOUDRON' => null], fn () => (require config_path('queue.php'))['runner']);

    expect($cloudron)->toBe('worker')->and($shared)->toBe('cron');
});

test('a queued job is processed by the scheduled queue:work command', function () {
    config(['queue.default' => 'database']);
    QueueHeartbeat::dispatch(now()->toIso8601String());

    expect(DB::table('jobs')->count())->toBe(1);

    Artisan::call('queue:work', ['--stop-when-empty' => true, '--max-time' => 50, '--tries' => 3]);

    expect(DB::table('jobs')->count())->toBe(0);
});

test('app:heartbeat records when the scheduler last ran', function () {
    $this->artisan('app:heartbeat')->assertSuccessful();

    expect(Heartbeat::lastSeen()?->diffInSeconds(now()))->toBeLessThan(5)
        ->and(Heartbeat::isStale())->toBeFalse();
});

test('Super Admins see a warning when the scheduler has not run for 10 minutes', function () {
    Installation::query()->update(['installed_at' => now()->subHour()]);
    cache()->put(Heartbeat::KEY, now()->subMinutes(11)->getTimestamp());
    $admin = User::factory()->superAdmin()->create(['two_factor_confirmed_at' => now(), 'two_factor_secret' => encrypt('x')]);

    $this->actingAs($admin)->get(route('admin.installation.show'))
        ->assertInertia(fn (Assert $page) => $page->where('cronStale', true)->where('cron.stale', true));
});

test('no warning while the scheduler is running', function () {
    Installation::query()->update(['installed_at' => now()->subHour()]);
    Heartbeat::record();
    $admin = User::factory()->superAdmin()->create(['two_factor_confirmed_at' => now(), 'two_factor_secret' => encrypt('x')]);

    $this->actingAs($admin)->get(route('admin.installation.show'))
        ->assertInertia(fn (Assert $page) => $page->where('cronStale', false));
});

test('a fresh install gets 10 minutes to set up cron before the warning', function () {
    Installation::query()->update(['installed_at' => now()->subMinutes(3)]);
    cache()->forget(Heartbeat::KEY);

    expect(Heartbeat::isStale())->toBeFalse();
});

test('ordinary users never see the cron warning', function () {
    Installation::query()->update(['installed_at' => now()->subHour()]);
    cache()->forget(Heartbeat::KEY);

    $this->actingAs(User::factory()->create())->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('cronStale', false));
});
