<?php

namespace App\Updates;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Chunked HTTP download with Range requests. Progress is the size of the
 * partial file, so an interrupted download resumes where it stopped.
 */
final class ReleaseDownloader
{
    /**
     * Download more of $url into $path. Returns true once $size bytes are there.
     */
    public static function continue(string $url, string $path, int $size, Deadline $deadline, int $chunkBytes): bool
    {
        clearstatcache(true, $path);
        $offset = is_file($path) ? (int) filesize($path) : 0;

        while ($offset < $size) {
            $end = min($offset + $chunkBytes, $size) - 1;

            try {
                $response = Http::timeout(60)->withHeaders(['Range' => "bytes={$offset}-{$end}"])->get($url);
            } catch (ConnectionException $e) {
                throw new InvalidRelease('The download was interrupted: '.$e->getMessage());
            }

            $body = $response->body();

            if ($response->status() === 200 && $offset === 0) {
                file_put_contents($path, $body);
            } elseif ($response->status() === 206) {
                file_put_contents($path, $body, FILE_APPEND);
            } else {
                throw new InvalidRelease("The download failed with HTTP {$response->status()}.");
            }

            clearstatcache(true, $path);
            $previous = $offset;
            $offset = (int) filesize($path);

            if ($offset <= $previous) {
                throw new InvalidRelease('The download stopped making progress; the file on the server does not match the release feed.');
            }

            if ($offset > $size) {
                throw new InvalidRelease('The download is larger than the release feed says.');
            }

            if ($deadline->passed()) {
                break;
            }
        }

        return $offset >= $size;
    }

    public static function fetch(string $url): string
    {
        try {
            $response = Http::timeout(30)->get($url);
        } catch (ConnectionException $e) {
            throw new InvalidRelease('Could not download '.basename($url).': '.$e->getMessage());
        }

        if (! $response->successful()) {
            throw new InvalidRelease('Could not download '.basename($url).": HTTP {$response->status()}.");
        }

        return $response->body();
    }
}
