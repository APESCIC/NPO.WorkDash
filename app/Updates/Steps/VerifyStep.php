<?php

namespace App\Updates\Steps;

use App\Updates\Engine\Step;
use App\Updates\Engine\StepContext;
use App\Updates\ReleaseVerifier;

final class VerifyStep implements Step
{
    public function name(): string
    {
        return 'verify';
    }

    public function run(StepContext $context): bool
    {
        $zip = (string) $context->run->stateValue('zip_path');

        ReleaseVerifier::fromConfig()->verify($zip, (string) file_get_contents("{$zip}.sig"), (string) $context->run->release['sha256']);

        $context->log('Checksum and signature are valid.');

        return true;
    }
}
