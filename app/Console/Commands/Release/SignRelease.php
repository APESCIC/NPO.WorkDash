<?php

namespace App\Console\Commands\Release;

use App\Updates\InvalidRelease;
use App\Updates\ReleaseSigner;
use Illuminate\Console\Command;

class SignRelease extends Command
{
    protected $signature = 'release:sign {file : Release zip to sign}';

    protected $description = 'Write <file>.sha256 and an Ed25519 <file>.sig using the RELEASE_SIGNING_KEY environment variable';

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        $key = (string) getenv('RELEASE_SIGNING_KEY');

        if ($key === '') {
            $this->error('RELEASE_SIGNING_KEY is not set.');

            return self::FAILURE;
        }

        try {
            $signature = ReleaseSigner::sign($file, $key);
        } catch (InvalidRelease $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        file_put_contents("{$file}.sig", $signature."\n");
        file_put_contents("{$file}.sha256", hash_file('sha256', $file).'  '.basename($file)."\n");

        $this->info("Signed {$file}.");

        return self::SUCCESS;
    }
}
