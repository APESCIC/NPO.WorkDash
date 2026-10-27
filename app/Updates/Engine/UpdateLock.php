<?php

namespace App\Updates\Engine;

use Illuminate\Support\Facades\File;

/**
 * A single-run file lock, so two requests never work on an update at once.
 */
final class UpdateLock
{
    /** @var resource|null */
    private $handle = null;

    public function acquire(): bool
    {
        $path = (string) config('updates.lock_path');
        File::ensureDirectoryExists(dirname($path));

        $handle = fopen($path, 'c');

        if ($handle === false || ! flock($handle, LOCK_EX | LOCK_NB)) {
            if ($handle !== false) {
                fclose($handle);
            }

            return false;
        }

        $this->handle = $handle;

        return true;
    }

    public function release(): void
    {
        if ($this->handle !== null) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
            $this->handle = null;
        }
    }
}
