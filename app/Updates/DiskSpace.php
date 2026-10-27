<?php

namespace App\Updates;

final class DiskSpace
{
    private static ?int $fake = null;

    /**
     * Free bytes at $path, or null when the host does not report it.
     */
    public static function free(string $path): ?int
    {
        if (self::$fake !== null) {
            return self::$fake;
        }

        $free = function_exists('disk_free_space') ? @disk_free_space($path) : false;

        return $free === false ? null : (int) $free;
    }

    public static function fake(?int $bytes): void
    {
        self::$fake = $bytes;
    }
}
