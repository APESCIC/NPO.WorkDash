<?php

namespace App\Console\Commands;

use App\Models\Installation;
use App\Models\UpdateRun;
use App\Updates\Engine\UpdateEngine;
use App\Updates\Engine\UpdateInProgress;
use App\Updates\FeedClient;
use App\Updates\InvalidRelease;
use App\Updates\Preflight;
use App\Updates\UpdateFeed;
use Illuminate\Console\Command;

class UpdateApp extends Command
{
    protected $signature = 'app:update
        {--check : Only show whether an update is available and run the pre-flight checks}
        {--channel= : stable or beta (defaults to UPDATES_CHANNEL)}';

    protected $description = 'Update NPO.WorkDash to the newest release on its channel (same engine as the web updater)';

    public function handle(UpdateEngine $engine): int
    {
        $run = $engine->active();

        if ($run !== null) {
            $this->info("Resuming the update to {$run->to_version}.");

            return $this->drive($engine, $run);
        }

        $channel = (string) ($this->option('channel') ?: config('updates.channel'));

        try {
            $feed = FeedClient::fromConfig(Installation::query()->value('installation_id'))->fetch($channel);
        } catch (InvalidRelease $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $release = UpdateFeed::latestFor($feed, (string) config('app.version'));

        if ($release === null) {
            $this->info('NPO.WorkDash '.config('app.version')." is up to date on the {$channel} channel.");

            return self::SUCCESS;
        }

        $this->info('Update available: '.config('app.version')." to {$release['version']} ({$channel}).");

        $checks = Preflight::check($release, $engine->layout());
        $this->table(['', 'Check', 'Result'], array_map(fn (array $item) => [strtoupper($item['status']), $item['label'], $item['detail']], $checks));

        if (! Preflight::passes($checks)) {
            $this->error('Pre-flight checks failed; nothing was changed.');

            return self::FAILURE;
        }

        if ($this->option('check')) {
            return self::SUCCESS;
        }

        try {
            $run = $engine->start($release, null, 'cli');
        } catch (UpdateInProgress $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return $this->drive($engine, $run);
    }

    private function drive(UpdateEngine $engine, UpdateRun $run): int
    {
        $reported = [];

        $run = $engine->runToCompletion($run, function (UpdateRun $run) use (&$reported) {
            foreach ($run->steps()->where('status', 'done')->get() as $step) {
                if (! in_array($step->id, $reported, true)) {
                    $reported[] = $step->id;
                    $this->line("  <info>done</info> {$step->position}. {$step->name}");
                }
            }
        });

        if ($run->status !== UpdateRun::SUCCEEDED) {
            $this->error("Update {$run->status}: {$run->error}");

            return self::FAILURE;
        }

        $this->info("Updated to {$run->to_version} in {$run->durationSeconds()} seconds.");

        return self::SUCCESS;
    }
}
