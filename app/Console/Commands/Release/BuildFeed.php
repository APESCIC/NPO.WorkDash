<?php

namespace App\Console\Commands\Release;

use App\Updates\UpdateFeed;
use Illuminate\Console\Command;

class BuildFeed extends Command
{
    protected $signature = 'release:feed
        {entries : Directory of feed entry JSON files, one per release}
        {output : Directory to write stable.json and beta.json into}';

    protected $description = 'Build and validate the stable and beta update feeds';

    public function handle(): int
    {
        $entries = [];

        foreach (glob(rtrim((string) $this->argument('entries'), '/\\').'/*.json') ?: [] as $file) {
            $entry = json_decode((string) file_get_contents($file), true);

            if (is_array($entry)) {
                $entries[] = $entry;
            }
        }

        $output = rtrim((string) $this->argument('output'), '/\\');

        if (! is_dir($output)) {
            mkdir($output, 0755, true);
        }

        $generatedAt = now()->utc()->toIso8601String();

        foreach (config('updates.channels') as $channel) {
            $feed = UpdateFeed::build($entries, $channel, $generatedAt);
            $errors = UpdateFeed::errors($feed);

            if ($errors !== []) {
                foreach ($errors as $error) {
                    $this->error("{$channel}: {$error}");
                }

                return self::FAILURE;
            }

            file_put_contents("{$output}/{$channel}.json", json_encode($feed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
            $this->info(sprintf('%s.json: %d releases', $channel, count($feed['releases'])));
        }

        return self::SUCCESS;
    }
}
