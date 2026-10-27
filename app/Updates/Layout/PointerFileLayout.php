<?php

namespace App\Updates\Layout;

use App\Updates\InvalidRelease;
use Illuminate\Support\Facades\File;

/**
 * Shared hosting: current.php names the live release. Switching rewrites that
 * one file (temp file, then rename), so it needs neither symlinks nor exec.
 */
final class PointerFileLayout implements ReleaseLayoutDriver
{
    /** Files in the web root that belong to the site, not to a release. */
    private const KEEP_IN_WEB_ROOT = ['workdash-path.php', 'check.php', 'RequirementsChecker.php'];

    private readonly string $root;

    /**
     * @param  list<string>  $webRoots
     */
    public function __construct(string $root, private readonly array $webRoots)
    {
        $this->root = rtrim(str_replace('\\', '/', $root), '/');
    }

    public function name(): string
    {
        return 'pointer';
    }

    public function root(): string
    {
        return $this->root;
    }

    public function current(): string
    {
        $path = require $this->pointer();

        return rtrim(str_replace('\\', '/', (string) $path), '/');
    }

    public function pathFor(string $version, int $runId): string
    {
        $path = "{$this->root}/releases/{$version}";

        return is_dir($path) && realpath($path) !== realpath($this->current()) ? "{$path}-{$runId}" : $path;
    }

    public function releases(): array
    {
        $releases = array_map(fn (string $path) => str_replace('\\', '/', $path), glob($this->root.'/releases/*', GLOB_ONLYDIR) ?: []);
        usort($releases, fn (string $a, string $b) => filemtime($b) <=> filemtime($a));

        return $releases;
    }

    public function activate(string $release): void
    {
        $release = rtrim(str_replace('\\', '/', $release), '/');

        if (! is_file($release.'/artisan')) {
            throw new InvalidRelease("{$release} is not a release folder.");
        }

        $relative = str_starts_with($release, $this->root.'/releases/')
            ? "__DIR__.'/".substr($release, strlen($this->root) + 1)."'"
            : var_export($release, true);

        $temp = $this->pointer().'.'.bin2hex(random_bytes(4)).'.tmp';
        file_put_contents($temp, "<?php\n\n// The live release. Updates rewrite only this file.\nreturn {$relative};\n");

        if (! @rename($temp, $this->pointer())) {
            @unlink($temp);

            throw new InvalidRelease('Could not switch current.php to the new release.');
        }

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($this->pointer(), true);
        }
    }

    public function publishAssets(string $publicDirectory, bool $entryPoint = true): void
    {
        $keep = $entryPoint ? self::KEEP_IN_WEB_ROOT : [...self::KEEP_IN_WEB_ROOT, 'index.php', '.htaccess'];

        foreach ($this->webRoots as $webRoot) {
            if (! is_dir($webRoot)) {
                continue;
            }

            foreach (File::allFiles($publicDirectory, true) as $file) {
                $relative = str_replace('\\', '/', $file->getRelativePathname());

                if (in_array($relative, $keep, true)) {
                    continue;
                }

                File::ensureDirectoryExists(dirname("{$webRoot}/{$relative}"));
                copy($file->getPathname(), "{$webRoot}/{$relative}");
            }
        }
    }

    private function pointer(): string
    {
        return $this->root.'/current.php';
    }
}
