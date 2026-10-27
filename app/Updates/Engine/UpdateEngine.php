<?php

namespace App\Updates\Engine;

use App\Models\UpdateRun;
use App\Models\UpdateStep;
use App\Models\User;
use App\Updates\Backup\DatabaseDump;
use App\Updates\Deadline;
use App\Updates\Events\UpdateFinished;
use App\Updates\InvalidRelease;
use App\Updates\Layout\LayoutResolver;
use App\Updates\Layout\ReleaseLayoutDriver;
use App\Updates\Steps\BackupStep;
use App\Updates\Steps\CleanupStep;
use App\Updates\Steps\DownloadStep;
use App\Updates\Steps\ExtractStep;
use App\Updates\Steps\HealthStep;
use App\Updates\Steps\MaintenanceOffStep;
use App\Updates\Steps\MaintenanceOnStep;
use App\Updates\Steps\MigrateStep;
use App\Updates\Steps\PreflightStep;
use App\Updates\Steps\SwapStep;
use App\Updates\Steps\VerifyStep;
use App\Updates\Version;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Runs an update as an ordered list of idempotent steps stored in the
 * database. Web requests call tick() once each; the CLI calls it in a loop.
 * A crash or closed browser leaves the run where it was, and the next tick
 * carries on from the last unfinished step.
 */
class UpdateEngine
{
    /** @var list<class-string<Step>> */
    public const STEPS = [
        PreflightStep::class,
        BackupStep::class,
        DownloadStep::class,
        VerifyStep::class,
        ExtractStep::class,
        MaintenanceOnStep::class,
        SwapStep::class,
        MigrateStep::class,
        HealthStep::class,
        MaintenanceOffStep::class,
        CleanupStep::class,
    ];

    private ?ReleaseLayoutDriver $layout = null;

    public function useLayout(?ReleaseLayoutDriver $layout): static
    {
        $this->layout = $layout;

        return $this;
    }

    public function layout(): ?ReleaseLayoutDriver
    {
        return $this->layout ??= LayoutResolver::detect();
    }

    public function active(): ?UpdateRun
    {
        return UpdateRun::query()->active()->latest('id')->first();
    }

    /**
     * Record a new update. Nothing changes on disk until it is ticked.
     *
     * @param  array<string, mixed>  $release  a feed entry
     */
    public function start(array $release, ?User $by, string $source): UpdateRun
    {
        $lock = new UpdateLock;

        if (! $lock->acquire() || $this->active() !== null) {
            $lock->release();

            throw new UpdateInProgress('Another update is already running.');
        }

        try {
            $run = UpdateRun::query()->create([
                'from_version' => (string) config('app.version'),
                'to_version' => (string) $release['version'],
                'channel' => Version::channel((string) $release['version']),
                'source' => $source,
                'status' => UpdateRun::PENDING,
                'started_by' => $by?->id,
                'release' => $release,
                'state' => [],
                'started_at' => now(),
            ]);

            foreach (self::STEPS as $position => $class) {
                $run->steps()->create([
                    'position' => $position + 1,
                    'name' => app($class)->name(),
                    'status' => UpdateStep::PENDING,
                ]);
            }
        } finally {
            $lock->release();
        }

        UpdateLog::write($run, "Update from {$run->from_version} to {$run->to_version} started from the {$source}".($by ? " by {$by->email}" : '').'.');

        return $run;
    }

    /**
     * Do up to one step's worth of work (within the step time budget).
     */
    public function tick(UpdateRun $run): UpdateRun
    {
        $lock = new UpdateLock;

        if (! $lock->acquire()) {
            return $run->fresh() ?? $run;
        }

        try {
            $run->refresh();

            if ($run->isFinished()) {
                return $run;
            }

            $step = $run->steps()->where('status', '!=', UpdateStep::DONE)->first();

            if ($step === null) {
                return $this->finish($run, UpdateRun::SUCCEEDED);
            }

            $layout = $this->layout();

            if ($layout === null) {
                throw new InvalidRelease('This copy was not installed from a release zip, so it cannot update itself.');
            }

            $run->update(['status' => UpdateRun::RUNNING]);
            $step->update(['status' => UpdateStep::RUNNING, 'attempts' => $step->attempts + 1, 'started_at' => $step->started_at ?? now()]);

            $handler = $this->handler($step->name);
            $context = new StepContext($run, $step, $layout, Deadline::fromConfig());

            if ($handler->run($context)) {
                $step->update(['status' => UpdateStep::DONE, 'finished_at' => now()]);
                UpdateLog::write($run, "Step {$step->position} {$step->name}: done.");

                if (! $run->steps()->where('status', '!=', UpdateStep::DONE)->exists()) {
                    return $this->finish($run, UpdateRun::SUCCEEDED);
                }
            }

            return $run->refresh();
        } catch (Throwable $e) {
            return $this->fail($run, $e);
        } finally {
            $lock->release();
        }
    }

    /**
     * Tick until the run finishes (CLI).
     *
     * @param  (callable(UpdateRun): void)|null  $progress
     */
    public function runToCompletion(UpdateRun $run, ?callable $progress = null): UpdateRun
    {
        while (! $run->isFinished()) {
            $run = $this->tick($run);

            if ($progress !== null) {
                $progress($run);
            }
        }

        return $run;
    }

    /**
     * Undo an update: switch back to the previous release and, if migrations
     * ran, restore the pre-update database backup.
     */
    public function rollback(UpdateRun $run, string $reason): UpdateRun
    {
        $layout = $this->layout();
        $previous = (string) $run->stateValue('previous_release');

        UpdateLog::write($run, "Rolling back: {$reason}");

        try {
            if ($layout !== null && $run->stateValue('swapped') && is_dir($previous)) {
                $layout->activate($previous);
                $layout->publishAssets($previous.'/public', entryPoint: false);
                UpdateLog::write($run, "Switched back to {$previous}.");
            }

            if ((int) $run->stateValue('migrated', 0) > 0) {
                $backup = $run->stateValue('backup_dir').'/database.sql';

                if (! is_file($backup)) {
                    throw new InvalidRelease("Migrations ran but the backup {$backup} is missing; restore the database from your hosting backup.");
                }

                (new DatabaseDump((string) (config('updates.backup_connection') ?? config('database.default'))))->restore($backup);
                UpdateLog::write($run, "Restored the database from {$backup}.");
            }
        } finally {
            if ($run->stateValue('maintenance_secret')) {
                Artisan::call('up');
            }
        }

        $run->mergeState(['swapped' => false, 'migrated' => 0]);

        return $this->finish($run, UpdateRun::ROLLED_BACK, $reason);
    }

    /**
     * Roll back the most recent successful update (app:rollback and the
     * Rollback button).
     */
    public function rollbackLatest(?User $by, string $source): UpdateRun
    {
        $run = UpdateRun::query()->where('status', UpdateRun::SUCCEEDED)->latest('id')->first();

        if ($run === null || ! is_dir((string) $run->stateValue('previous_release'))) {
            throw new InvalidRelease('There is no update to roll back, or its previous release has been removed.');
        }

        if ($this->active() !== null) {
            throw new UpdateInProgress('An update is running; wait for it to finish first.');
        }

        Artisan::call('down', ['--retry' => 15]);
        $run->mergeState(['swapped' => true, 'maintenance_secret' => $run->stateValue('maintenance_secret') ?? 'rollback']);

        return $this->rollback($run, sprintf('Rolled back from the %s%s.', $source, $by ? " by {$by->email}" : ''));
    }

    private function fail(UpdateRun $run, Throwable $e): UpdateRun
    {
        $run->refresh();
        $step = $run->steps()->where('status', UpdateStep::RUNNING)->first();
        $step?->update(['status' => UpdateStep::FAILED, 'output' => $e->getMessage(), 'finished_at' => now()]);
        UpdateLog::write($run, 'Failed'.($step ? " at {$step->name}" : '').": {$e->getMessage()}");

        if ($run->stateValue('swapped') || $run->stateValue('maintenance_secret')) {
            try {
                return $this->rollback($run, 'Automatic rollback after a failure: '.$e->getMessage());
            } catch (Throwable $rollbackError) {
                UpdateLog::write($run, "Rollback failed: {$rollbackError->getMessage()}");

                return $this->finish($run, UpdateRun::FAILED, $e->getMessage().' Rollback also failed: '.$rollbackError->getMessage());
            }
        }

        return $this->finish($run, UpdateRun::FAILED, $e->getMessage());
    }

    private function finish(UpdateRun $run, string $status, ?string $error = null): UpdateRun
    {
        $run->update(['status' => $status, 'error' => $error, 'finished_at' => now()]);
        UpdateLog::write($run, "Finished: {$status}.");
        UpdateFinished::dispatch($run);

        return $run;
    }

    private function handler(string $name): Step
    {
        foreach (self::STEPS as $class) {
            $step = app($class);

            if ($step->name() === $name) {
                return $step;
            }
        }

        throw new InvalidRelease("Unknown update step {$name}.");
    }
}
