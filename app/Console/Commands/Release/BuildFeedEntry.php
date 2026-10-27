<?php

namespace App\Console\Commands\Release;

use App\Updates\UpdateFeed;
use Illuminate\Console\Command;

class BuildFeedEntry extends Command
{
    protected $signature = 'release:feed-entry
        {zip : Built release zip}
        {manifest : manifest.json of that release}
        {output : Where to write the feed entry JSON}
        {--notes= : File holding the changelog section for this release}';

    protected $description = 'Write the update feed entry for one release';

    public function handle(): int
    {
        $manifest = json_decode((string) @file_get_contents((string) $this->argument('manifest')), true);
        $zip = (string) $this->argument('zip');

        if (! is_array($manifest) || ! is_file($zip)) {
            $this->error('The zip or manifest could not be read.');

            return self::FAILURE;
        }

        $notes = $this->option('notes') ? (string) @file_get_contents((string) $this->option('notes')) : '';
        $entry = UpdateFeed::entry($manifest, $zip, $notes);

        file_put_contents((string) $this->argument('output'), json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        $this->info("Wrote feed entry for {$entry['version']} ({$entry['channel']}).");

        return self::SUCCESS;
    }
}
