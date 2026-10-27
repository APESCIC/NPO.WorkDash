<?php

namespace App\Updates;

use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;

final class UpdateFeed
{
    public const SCHEMA = 1;

    /**
     * One feed entry for a built release zip.
     *
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    public static function entry(array $manifest, string $zipPath, string $changelog): array
    {
        $version = (string) $manifest['version'];
        $base = rtrim((string) config('updates.download_base_url'), '/')."/v{$version}";
        $zip = "npo-workdash-{$version}.zip";

        return [
            'version' => $version,
            'channel' => Version::channel($version),
            'released_at' => $manifest['released_at'],
            'changelog' => trim($changelog),
            'size' => (int) filesize($zipPath),
            'sha256' => (string) hash_file('sha256', $zipPath),
            'download_url' => "{$base}/{$zip}",
            'signature_url' => "{$base}/{$zip}.sig",
            'requirements' => $manifest['requirements'],
            'min_update_from' => $manifest['min_update_from'],
        ];
    }

    /**
     * The feed for one channel, newest release first. Stable feeds list only
     * stable releases; the beta feed lists both.
     *
     * @param  list<array<string, mixed>>  $entries
     * @return array<string, mixed>
     */
    public static function build(array $entries, string $channel, string $generatedAt): array
    {
        $releases = array_values(array_filter(
            $entries,
            fn (array $entry) => $channel === 'beta' || $entry['channel'] === 'stable',
        ));

        usort($releases, fn (array $a, array $b) => version_compare($b['version'], $a['version']));

        return [
            'schema' => self::SCHEMA,
            'channel' => $channel,
            'generated_at' => $generatedAt,
            'releases' => $releases,
        ];
    }

    /**
     * Schema violations as "path: message" strings; empty when valid.
     *
     * @param  array<string, mixed>  $feed
     * @return list<string>
     */
    public static function errors(array $feed): array
    {
        $schema = json_decode((string) file_get_contents(resource_path('schemas/update-feed.v1.json')));
        $data = json_decode((string) json_encode($feed));

        $validator = new Validator;
        $validator->validate($data, $schema, Constraint::CHECK_MODE_NORMAL);

        return array_map(
            fn (array $error) => trim(($error['property'] ?: 'feed').': '.$error['message']),
            $validator->getErrors(),
        );
    }

    /**
     * The newest release on the feed that the given version can update to.
     *
     * @param  array<string, mixed>  $feed
     * @return array<string, mixed>|null
     */
    public static function latestFor(array $feed, string $currentVersion): ?array
    {
        foreach ($feed['releases'] ?? [] as $release) {
            if (Version::isNewer($release['version'], $currentVersion)) {
                return $release;
            }
        }

        return null;
    }
}
