<?php

namespace App\Console\Commands\Release;

use App\Updates\ReleaseManifest;
use Illuminate\Console\Command;

class BuildManifest extends Command
{
    protected $signature = 'release:manifest
        {path : Directory holding the staged release}
        {--release-version= : Version being released (defaults to the VERSION file in the path)}';

    protected $description = 'Write manifest.json with requirements, migrations and per-file SHA-256 into a staged release';

    public function handle(): int
    {
        $path = rtrim((string) $this->argument('path'), '/\\');
        $version = (string) ($this->option('release-version') ?: trim((string) @file_get_contents("{$path}/VERSION")));

        if (! is_dir($path) || $version === '') {
            $this->error("No staged release with a VERSION file at {$path}.");

            return self::FAILURE;
        }

        $manifest = ReleaseManifest::build($path, $version, now()->utc()->toIso8601String());

        file_put_contents("{$path}/".ReleaseManifest::FILE, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        $this->info(sprintf('Wrote manifest for %s (%d files, %d migrations).', $version, count($manifest['files']), count($manifest['migrations'])));

        return self::SUCCESS;
    }
}
