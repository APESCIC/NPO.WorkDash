<?php

namespace App\Updates;

use App\Install\RequirementsChecker;
use App\Updates\Layout\ReleaseLayoutDriver;

/**
 * Checks that must pass before an update changes anything. Shown on the
 * updater screen and by `app:update --check`, and repeated as the first step.
 */
final class Preflight
{
    /**
     * @param  array<string, mixed>  $release  a feed entry
     * @return list<array{key: string, label: string, status: string, detail: string}>
     */
    public static function check(array $release, ?ReleaseLayoutDriver $layout): array
    {
        $current = (string) config('app.version');
        $requirements = $release['requirements'] ?? [];
        $items = [];

        $items[] = self::item('version', 'Newer version', Version::isNewer($release['version'], $current), "{$current} to {$release['version']}", "{$release['version']} is not newer than {$current}.");

        $from = (string) ($release['min_update_from'] ?? '0.0.0');
        $items[] = self::item('update_from', 'Update path', version_compare($current, $from, '>='), "Supported from {$from}", "Update to {$from} first; {$release['version']} cannot be installed straight from {$current}.");

        $php = (string) ($requirements['php'] ?? '0');
        $items[] = self::item('php', 'PHP version', version_compare(PHP_VERSION, $php, '>='), PHP_VERSION, "PHP {$php} or newer is required; this server runs ".PHP_VERSION.'.');

        $missing = array_values(array_filter($requirements['extensions'] ?? [], fn (string $extension) => ! extension_loaded($extension)));
        $items[] = self::item('extensions', 'PHP extensions', $missing === [], 'All loaded', 'Missing: '.implode(', ', $missing));

        $items[] = self::item('download', 'Download available', isset($release['download_url'], $release['signature_url']), 'Signed download available', 'The update server withheld the download link (licence required).');

        $items[] = self::item('layout', 'Release layout', $layout !== null, $layout ? "{$layout->name()} layout at {$layout->root()}" : '', 'This copy was not installed from a release zip (for example a git checkout), so it cannot update itself. Deploy the new version instead.');

        if ($layout !== null) {
            $writable = is_writable($layout->root()) && is_writable($layout->root().'/releases');
            $items[] = self::item('writable', 'Release folder writable', $writable, 'Writable', "{$layout->root()}/releases must be writable by PHP.");

            $needed = (int) ($release['size'] ?? 0) * 4;
            $free = DiskSpace::free($layout->root());
            $items[] = self::item('disk', 'Free disk space', $free === null || $free >= $needed, $free === null ? 'Not reported by this host' : self::megabytes($free).' free', 'Not enough disk space: '.self::megabytes($needed).' is needed for the download, the new release and a backup; '.self::megabytes((int) $free).' is free.');
        }

        return $items;
    }

    /**
     * @param  list<array{status: string}>  $items
     */
    public static function passes(array $items): bool
    {
        return RequirementsChecker::summary($items) !== RequirementsChecker::FAIL;
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string}
     */
    private static function item(string $key, string $label, bool $ok, string $pass, string $fail): array
    {
        return ['key' => $key, 'label' => $label, 'status' => $ok ? 'pass' : 'fail', 'detail' => $ok ? $pass : $fail];
    }

    private static function megabytes(int $bytes): string
    {
        return number_format($bytes / 1024 / 1024).' MB';
    }
}
