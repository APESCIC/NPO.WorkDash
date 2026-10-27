<?php

namespace App\Console\Commands\Release;

use App\Updates\InvalidRelease;
use App\Updates\ReleaseVerifier;
use Illuminate\Console\Command;

class VerifyRelease extends Command
{
    protected $signature = 'release:verify {file : Release zip with its .sha256 and .sig files beside it}';

    protected $description = 'Check a release zip against its checksum and the release signing key';

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        $checksum = @file_get_contents("{$file}.sha256");
        $signature = @file_get_contents("{$file}.sig");

        if (! is_file($file) || $checksum === false || $signature === false) {
            $this->error("Expected {$file} with {$file}.sha256 and {$file}.sig beside it.");

            return self::FAILURE;
        }

        try {
            ReleaseVerifier::fromConfig()->verify($file, $signature, strtok($checksum, ' ') ?: '');
        } catch (InvalidRelease $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(basename($file).' is intact and signed by the release key.');

        return self::SUCCESS;
    }
}
