<?php

namespace App\Install;

use App\Models\Installation;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Whether this app has been installed. Bound as a scoped singleton so the
 * answer is computed at most once per request.
 */
class InstallState
{
    private ?bool $installed = null;

    public function isInstalled(): bool
    {
        if ($this->installed !== null) {
            return $this->installed;
        }

        if (is_file($this->lockPath())) {
            return $this->installed = true;
        }

        if ($this->hasDatabaseRecord()) {
            $this->writeLock();

            return $this->installed = true;
        }

        return $this->installed = false;
    }

    public function hasDatabaseRecord(): bool
    {
        try {
            return Schema::hasTable('installations') && Installation::query()->exists();
        } catch (Throwable) {
            return false;
        }
    }

    public function writeLock(): void
    {
        $path = $this->lockPath();

        if (! is_dir(dirname($path))) {
            @mkdir(dirname($path), 0755, true);
        }

        @file_put_contents($path, now()->toIso8601String()."\n");
        $this->installed = true;
    }

    public function lockPath(): string
    {
        return (string) config('install.lock_path');
    }

    public function forget(): void
    {
        $this->installed = null;
    }
}
