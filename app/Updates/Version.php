<?php

namespace App\Updates;

final class Version
{
    /**
     * Pre-release versions (1.2.0-beta.1) ship on the beta channel.
     */
    public static function channel(string $version): string
    {
        return str_contains($version, '-') ? 'beta' : 'stable';
    }

    public static function isNewer(string $candidate, string $current): bool
    {
        return version_compare($candidate, $current, '>');
    }

    public static function fromTag(string $tag): string
    {
        return ltrim($tag, 'v');
    }
}
