<?php

namespace App\Updates\Steps;

use App\Updates\Engine\Step;
use App\Updates\Engine\StepContext;
use App\Updates\ReleaseDownloader;
use Illuminate\Support\Facades\File;

final class DownloadStep implements Step
{
    public function name(): string
    {
        return 'download';
    }

    public function run(StepContext $context): bool
    {
        $release = $context->run->release;
        $zip = $context->workPath("npo-workdash-{$release['version']}.zip");
        File::ensureDirectoryExists(dirname($zip));
        $context->run->mergeState(['zip_path' => $zip]);

        $done = ReleaseDownloader::continue(
            (string) $release['download_url'],
            $zip,
            (int) $release['size'],
            $context->deadline,
            (int) config('updates.download_chunk_bytes'),
        );

        clearstatcache(true, $zip);
        $context->log(sprintf('Downloaded %s of %s bytes.', number_format((int) @filesize($zip)), number_format((int) $release['size'])));

        if (! $done) {
            return false;
        }

        file_put_contents("{$zip}.sig", ReleaseDownloader::fetch((string) $release['signature_url']));

        return true;
    }
}
