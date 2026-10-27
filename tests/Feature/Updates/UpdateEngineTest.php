<?php

use App\Models\UpdateRun;
use App\Models\UpdateStep;
use App\Updates\DiskSpace;
use App\Updates\Engine\UpdateEngine;
use App\Updates\Engine\UpdateInProgress;
use App\Updates\Engine\UpdateLog;
use App\Updates\InvalidRelease;
use App\Updates\ReleaseExtractor;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Tests\Support\UpdateFixture;

beforeEach(function () {
    $this->fixture = new UpdateFixture;
    $this->engine = app(UpdateEngine::class);
});

afterEach(fn () => $this->fixture->cleanUp());

test('an update runs every step and switches to the new release', function () {
    $run = $this->engine->runToCompletion($this->engine->start($this->fixture->release, null, 'cli'));

    expect($run->status)->toBe(UpdateRun::SUCCEEDED)
        ->and($run->steps->pluck('status')->unique()->all())->toBe([UpdateStep::DONE])
        ->and($this->fixture->currentRelease())->toBe("{$this->fixture->root}/releases/9.9.0")
        ->and(file_get_contents("{$this->fixture->root}/public/build/app-9.9.0.js"))->toBe('new asset')
        ->and(file_get_contents("{$this->fixture->root}/public/index.php"))->toBe('<?php // new starter')
        ->and(file_get_contents("{$this->fixture->root}/public/workdash-path.php"))->toBe('<?php return dirname(__DIR__);')
        ->and(app()->isDownForMaintenance())->toBeFalse();
});

test('every step and its result is logged', function () {
    $run = $this->engine->runToCompletion($this->engine->start($this->fixture->release, null, 'cli'));
    $log = UpdateLog::read($run);

    foreach (UpdateEngine::STEPS as $position => $class) {
        expect($log)->toContain('Step '.($position + 1).' '.app($class)->name().': done.');
    }

    expect($log)->toContain('Finished: succeeded.')
        ->and(UpdateLog::path($run))->toStartWith(config('updates.log_path'));
});

test('a second update cannot start while one is running', function () {
    $this->engine->start($this->fixture->release, null, 'web');

    $this->engine->start($this->fixture->release, null, 'cli');
})->throws(UpdateInProgress::class);

test('a crashed step resumes from the last finished step', function () {
    config(['updates.download_chunk_bytes' => 512, 'updates.step_budget_seconds' => 0]);
    $run = $this->engine->start($this->fixture->release, null, 'web');

    for ($i = 0; $i < 200 && ! is_file((string) $run->stateValue('zip_path')); $i++) {
        $run = $this->engine->tick($run);
    }

    $download = $run->steps()->where('name', 'download')->first();
    expect($download->status)->toBe(UpdateStep::RUNNING)
        ->and(filesize($run->stateValue('zip_path')))->toBeGreaterThan(0)
        ->toBeLessThan($this->fixture->release['size']);

    // A fresh process (as after PHP was killed) picks the same run up.
    app()->forgetInstance(UpdateEngine::class);
    $engine = app(UpdateEngine::class)->useLayout($this->fixture->layout());
    $run = $engine->runToCompletion(UpdateRun::query()->sole());

    expect($run->status)->toBe(UpdateRun::SUCCEEDED)
        ->and($run->steps()->where('name', 'preflight')->value('attempts'))->toBe(1)
        ->and($run->steps()->where('name', 'download')->value('attempts'))->toBeGreaterThan(1);
});

test('each tick stays inside the step time budget', function () {
    expect(config('updates.step_budget_seconds'))->toBeLessThan(20);

    $run = $this->engine->start($this->fixture->release, null, 'web');

    while (! $run->isFinished()) {
        $started = microtime(true);
        $run = $this->engine->tick($run);

        expect(microtime(true) - $started)->toBeLessThan(20);
    }
});

test('a tampered zip is rejected before extraction and the live release is untouched', function () {
    $this->fixture->buildZip(tamper: true);

    $run = $this->engine->runToCompletion($this->engine->start($this->fixture->release, null, 'web'));

    expect($run->status)->toBe(UpdateRun::FAILED)
        ->and($run->steps()->where('name', 'verify')->value('status'))->toBe(UpdateStep::FAILED)
        ->and($run->steps()->where('name', 'extract')->value('status'))->toBe(UpdateStep::PENDING)
        ->and(is_dir("{$this->fixture->root}/releases/9.9.0"))->toBeFalse()
        ->and($this->fixture->currentRelease())->toBe("{$this->fixture->root}/releases/1.0.0");
});

test('zip entries with ../ or absolute paths are rejected', function (string $name) {
    $zipPath = "{$this->fixture->root}/build/evil.zip";
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE);
    $zip->addFromString('npo-workdash/releases/9.9.0/artisan', '<?php');
    $zip->addFromString($name, 'owned');
    $zip->close();

    expect(fn () => ReleaseExtractor::scan($zipPath))->toThrow(InvalidRelease::class, 'unsafe path');
})->with(['npo-workdash/../../evil.php', '/etc/evil', 'C:/evil.php', 'npo-workdash/releases/../../../evil.php', 'outside/evil.php']);

test('low disk space aborts before anything changes', function () {
    DiskSpace::fake(1024);

    $run = $this->engine->runToCompletion($this->engine->start($this->fixture->release, null, 'web'));

    expect($run->status)->toBe(UpdateRun::FAILED)
        ->and($run->error)->toContain('Not enough disk space')
        ->and($run->steps()->where('name', 'backup')->value('status'))->toBe(UpdateStep::PENDING)
        ->and(File::isDirectory(config('updates.backup_path')))->toBeFalse()
        ->and($this->fixture->currentRelease())->toBe("{$this->fixture->root}/releases/1.0.0");
});

test('a failed health check rolls back automatically and keeps the site in maintenance until then', function () {
    $this->fixture->healthy(false);

    $run = $this->engine->runToCompletion($this->engine->start($this->fixture->release, null, 'web'));

    expect($run->status)->toBe(UpdateRun::ROLLED_BACK)
        ->and($run->steps()->where('name', 'health')->value('status'))->toBe(UpdateStep::FAILED)
        ->and($run->steps()->where('name', 'maintenance_off')->value('status'))->toBe(UpdateStep::PENDING)
        ->and($this->fixture->currentRelease())->toBe("{$this->fixture->root}/releases/1.0.0")
        ->and(app()->isDownForMaintenance())->toBeFalse()
        ->and(UpdateLog::read($run))->toContain('Rolling back');
});

test('the previous release is kept until the next successful update', function () {
    File::ensureDirectoryExists("{$this->fixture->root}/releases/0.9.0");
    file_put_contents("{$this->fixture->root}/releases/0.9.0/artisan", '<?php');
    touch("{$this->fixture->root}/releases/0.9.0", time() - 3600);

    $this->engine->runToCompletion($this->engine->start($this->fixture->release, null, 'cli'));

    expect(is_dir("{$this->fixture->root}/releases/1.0.0"))->toBeTrue()
        ->and(is_dir("{$this->fixture->root}/releases/9.9.0"))->toBeTrue()
        ->and(is_dir("{$this->fixture->root}/releases/0.9.0"))->toBeFalse();
});

test('the pointer-file swap needs neither symlinks nor exec', function () {
    $source = (string) file_get_contents(app_path('Updates/Layout/PointerFileLayout.php'));

    expect($source)->not->toMatch('/\b(symlink|link|exec|shell_exec|proc_open|passthru|system)\s*\(/');
});

test('no update is started on a schedule', function () {
    $commands = collect(app(Schedule::class)->events())->map(fn ($event) => (string) $event->command);

    expect($commands->filter(fn (string $command) => str_contains($command, 'app:update')))->toBeEmpty();
});
