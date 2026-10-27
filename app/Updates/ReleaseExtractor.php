<?php

namespace App\Updates;

use ZipArchive;

/**
 * Batched, path-checked extraction of a verified release zip.
 */
final class ReleaseExtractor
{
    public const PREFIX = 'npo-workdash/';

    /**
     * Every entry, after rejecting the whole zip if any entry could escape
     * the target folder.
     *
     * @return array{entries: list<string>, bytes: int}
     */
    public static function scan(string $zipPath): array
    {
        $zip = self::open($zipPath);
        $entries = [];
        $bytes = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = (string) ($stat['name'] ?? '');

            if (! self::isSafe($name)) {
                $zip->close();

                throw new InvalidRelease("The release zip contains an unsafe path ({$name}) and was rejected.");
            }

            $entries[] = $name;
            $bytes += (int) ($stat['size'] ?? 0);
        }

        $zip->close();

        return ['entries' => $entries, 'bytes' => $bytes];
    }

    public static function isSafe(string $name): bool
    {
        if ($name === '' || str_contains($name, '\\') || str_contains($name, "\0")) {
            return false;
        }

        if (str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name)) {
            return false;
        }

        if (! str_starts_with($name, self::PREFIX)) {
            return false;
        }

        return ! in_array('..', explode('/', $name), true);
    }

    /**
     * Extract the given entries into $target.
     *
     * @param  list<string>  $entries
     */
    public static function extract(string $zipPath, string $target, array $entries): void
    {
        $zip = self::open($zipPath);

        if ($entries !== [] && ! $zip->extractTo($target, $entries)) {
            $zip->close();

            throw new InvalidRelease('Extracting the release failed (is the disk full?).');
        }

        $zip->close();
    }

    private static function open(string $zipPath): ZipArchive
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new InvalidRelease('The release zip cannot be opened.');
        }

        return $zip;
    }
}
