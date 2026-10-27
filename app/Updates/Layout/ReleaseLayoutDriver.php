<?php

namespace App\Updates\Layout;

/**
 * How releases sit on disk and how the live one is switched.
 */
interface ReleaseLayoutDriver
{
    public function name(): string;

    public function root(): string;

    /**
     * Absolute path of the live release.
     */
    public function current(): string;

    /**
     * Where a new release of this version is extracted.
     */
    public function pathFor(string $version, int $runId): string;

    /**
     * Release folders, newest first.
     *
     * @return list<string>
     */
    public function releases(): array;

    /**
     * Make the given release live, atomically.
     */
    public function activate(string $release): void;

    /**
     * Copy web assets into the web roots that serve the app. A release's own
     * public/ has the plain Laravel index.php, which must not replace the
     * starter entry point, so pass $entryPoint = false for those.
     */
    public function publishAssets(string $publicDirectory, bool $entryPoint = true): void;
}
