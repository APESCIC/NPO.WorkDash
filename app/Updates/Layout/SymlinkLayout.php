<?php

namespace App\Updates\Layout;

use App\Updates\InvalidRelease;

/**
 * Cloudron and VPS: a symlink (for example /app/data/workdash) points at the
 * live release, the layout deploy/cloudron/deploy.sh uses. The web root
 * follows the symlink, so there are no assets to copy.
 */
final class SymlinkLayout implements ReleaseLayoutDriver
{
    private readonly string $root;

    public function __construct(string $root, private readonly string $link)
    {
        $this->root = rtrim(str_replace('\\', '/', $root), '/');
    }

    public function name(): string
    {
        return 'symlink';
    }

    public function root(): string
    {
        return $this->root;
    }

    public function current(): string
    {
        return rtrim(str_replace('\\', '/', (string) realpath($this->link)), '/');
    }

    public function pathFor(string $version, int $runId): string
    {
        return "{$this->root}/releases/npo-workdash-{$version}-update-{$runId}";
    }

    public function releases(): array
    {
        $releases = glob($this->root.'/releases/*', GLOB_ONLYDIR) ?: [];
        usort($releases, fn (string $a, string $b) => filemtime($b) <=> filemtime($a));

        return $releases;
    }

    public function activate(string $release): void
    {
        if (! is_file($release.'/artisan')) {
            throw new InvalidRelease("{$release} is not a release folder.");
        }

        $next = $this->link.'.next';
        @unlink($next);

        if (! @symlink($release, $next) || ! @rename($next, $this->link)) {
            @unlink($next);

            throw new InvalidRelease("Could not switch {$this->link} to the new release.");
        }
    }

    public function publishAssets(string $publicDirectory, bool $entryPoint = true): void
    {
        //
    }
}
