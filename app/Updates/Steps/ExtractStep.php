<?php

namespace App\Updates\Steps;

use App\Updates\DiskSpace;
use App\Updates\Engine\Step;
use App\Updates\Engine\StepContext;
use App\Updates\InvalidRelease;
use App\Updates\ReleaseExtractor;
use App\Updates\ReleaseManifest;
use Illuminate\Support\Facades\File;

final class ExtractStep implements Step
{
    public function name(): string
    {
        return 'extract';
    }

    public function run(StepContext $context): bool
    {
        $version = (string) $context->run->release['version'];
        $zip = (string) $context->run->stateValue('zip_path');
        $staging = $context->workPath('staging');
        $prefix = ReleaseExtractor::PREFIX;

        $scan = ReleaseExtractor::scan($zip);
        $wanted = array_values(array_filter($scan['entries'], fn (string $name) => str_starts_with($name, "{$prefix}releases/{$version}/") || str_starts_with($name, "{$prefix}public/")));
        $state = $context->step->state ?? [];

        if (! ($state['checked'] ?? false)) {
            $free = DiskSpace::free($context->layout->root());

            if ($free !== null && $free < (int) ($scan['bytes'] * 1.2)) {
                throw new InvalidRelease('Not enough disk space to extract the release; the live site was not changed.');
            }

            File::deleteDirectory($staging);
            File::ensureDirectoryExists($staging);
            $context->step->mergeState(['checked' => true, 'index' => 0]);
            $state = $context->step->state;
        }

        $index = (int) $state['index'];
        $batch = (int) config('updates.extract_batch');

        while ($index < count($wanted)) {
            ReleaseExtractor::extract($zip, $staging, array_slice($wanted, $index, $batch));
            $index = min($index + $batch, count($wanted));
            $context->step->mergeState(['index' => $index]);

            if ($context->deadline->passed() && $index < count($wanted)) {
                $context->log("Extracted {$index} of ".count($wanted).' files.');

                return false;
            }
        }

        $extracted = "{$staging}/{$prefix}releases/{$version}";

        if (! is_file("{$extracted}/artisan")) {
            throw new InvalidRelease("The zip does not contain releases/{$version}.");
        }

        $target = $context->layout->pathFor($version, $context->run->id);

        if (! is_dir($target)) {
            if (! @rename($extracted, $target)) {
                throw new InvalidRelease("Could not move the release into {$target}.");
            }
        }

        $manifest = json_decode((string) @file_get_contents("{$target}/".ReleaseManifest::FILE), true);
        $mismatches = is_array($manifest) ? ReleaseManifest::mismatches($target, $manifest) : ['manifest.json'];

        if ($mismatches !== []) {
            File::deleteDirectory($target);

            throw new InvalidRelease('Extracted files do not match the manifest: '.implode(', ', array_slice($mismatches, 0, 5)));
        }

        $context->run->mergeState(['release_path' => $target, 'staged_public' => "{$staging}/{$prefix}public"]);
        $context->log("Release {$version} staged in {$target}.");

        return true;
    }
}
