<?php

namespace App\Install;

final class PhpBinary
{
    /**
     * The PHP CLI binary that cron should run. Web requests often run under
     * php-fpm or CGI, whose binary cannot run artisan, so control-panel CLI
     * paths for the same PHP version are tried first.
     *
     * @param  list<string>|null  $candidates
     */
    public static function detect(?string $current = PHP_BINARY, ?array $candidates = null): string
    {
        $current = (string) $current;

        if ($current !== '' && ! preg_match('/(fpm|cgi|httpd|apache|lsphp)/i', basename($current)) && @is_executable($current)) {
            return $current;
        }

        $version = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;
        $compact = PHP_MAJOR_VERSION.PHP_MINOR_VERSION;

        $candidates ??= array_values(array_filter([
            $current !== '' ? preg_replace('#/sbin/php-fpm[0-9.]*$#', '/bin/php', $current) : null,
            "/opt/cpanel/ea-php{$compact}/root/usr/bin/php",
            "/opt/alt/php{$compact}/usr/bin/php",
            "/opt/plesk/php/{$version}/bin/php",
            "/usr/local/php{$compact}/bin/php",
            "/usr/local/bin/php{$version}",
            "/usr/bin/php{$version}",
            '/usr/local/bin/php',
            '/usr/bin/php',
        ]));

        foreach ($candidates as $candidate) {
            if ($candidate !== $current && @is_executable($candidate)) {
                return $candidate;
            }
        }

        return 'php';
    }

    /**
     * The crontab line that runs the scheduler every minute.
     */
    public static function cronLine(string $php, string $artisan): string
    {
        return sprintf('* * * * * %s %s schedule:run >> /dev/null 2>&1', $php, $artisan);
    }
}
