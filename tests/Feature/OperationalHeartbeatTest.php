<?php

use App\Jobs\QueueHeartbeat;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

test('app:queue-check dispatches a queue heartbeat job', function () {
    Queue::fake();

    $this->artisan('app:queue-check')->assertSuccessful();

    Queue::assertPushed(QueueHeartbeat::class);
});

test('the queue heartbeat job logs when it is processed', function () {
    Log::spy();

    (new QueueHeartbeat('2026-10-27T00:00:00+00:00'))->handle();

    Log::shouldHaveReceived('info')
        ->once()
        ->withArgs(fn (string $message, array $context) => $message === 'Queue heartbeat processed'
            && $context['dispatched_at'] === '2026-10-27T00:00:00+00:00');
});

test('app:heartbeat logs a scheduler heartbeat', function () {
    Log::spy();

    $this->artisan('app:heartbeat')->assertSuccessful();

    Log::shouldHaveReceived('info')->once()->withArgs(fn (string $message) => $message === 'Scheduler heartbeat');
});

test('the scheduler heartbeat is scheduled every minute', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn (Event $event) => str_contains((string) $event->command, 'app:heartbeat'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('* * * * *');
});

test('requests forwarded by the Cloudron proxy over HTTPS generate HTTPS URLs', function () {
    $this->get('/', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'workdash.example.test'])
        ->assertOk();

    expect(request()->isSecure())->toBeTrue()
        ->and(url('/login'))->toStartWith('https://');
});

test('the home page responds with 200 for the Cloudron health check', function () {
    $this->get('/')->assertOk();
});
