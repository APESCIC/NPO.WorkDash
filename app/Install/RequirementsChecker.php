<?php

namespace App\Install;

use PDO;

/**
 * Server requirements check shared by the standalone check.php, the install
 * wizard and `php artisan app:check`.
 *
 * Must stay free of framework dependencies and PHP 8-only syntax so that
 * check.php can run on its own and report an outdated PHP instead of failing
 * to parse.
 */
class RequirementsChecker
{
    const PASS = 'pass';

    const WARN = 'warn';

    const FAIL = 'fail';

    /** @var array<string, mixed> */
    private $options;

    /**
     * @param  array<string, mixed>  $options  base_path, app_url, php, mysql, mariadb, extensions, writable, feed_host
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge([
            'base_path' => null,
            'app_url' => null,
            'php' => '8.3.0',
            'mysql' => '8.0.0',
            'mariadb' => '10.6.0',
            'extensions' => [
                'ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'mbstring', 'openssl',
                'pcre', 'pdo', 'pdo_mysql', 'session', 'tokenizer', 'xml', 'zip',
            ],
            'writable' => [],
            'feed_host' => 'apescic.github.io',
            'outbound' => true,
        ], $options);
    }

    /**
     * Every server check, in display order.
     *
     * @return list<array<string, mixed>>
     */
    public function run(): array
    {
        $items = [$this->checkPhpVersion()];

        foreach ($this->options['extensions'] as $extension) {
            $items[] = $this->checkExtension($extension);
        }

        $items[] = $this->checkDisabledFunctions();

        foreach ($this->options['writable'] as $label => $path) {
            $items[] = $this->checkWritable($label, $path);
        }

        $items[] = $this->checkMemory();
        $items[] = $this->checkExecutionTime();
        $items[] = $this->checkDisk();
        $items[] = $this->checkHttps();
        $items[] = $this->checkRewrite();
        $items[] = $this->checkOutbound();

        return $items;
    }

    /**
     * Database version and charset checks for an open connection.
     *
     * @return list<array<string, mixed>>
     */
    public function checkDatabase(PDO $pdo): array
    {
        $version = (string) $pdo->query('select version()')->fetchColumn();
        $charsetRow = $pdo->query("show variables like 'character_set_database'")->fetch(PDO::FETCH_NUM);
        $charset = is_array($charsetRow) ? (string) $charsetRow[1] : '';

        return [
            $this->checkDatabaseVersion($version),
            $charset === 'utf8mb4'
                ? $this->item('db_charset', 'Database character set', self::PASS, 'utf8mb4')
                : $this->item('db_charset', 'Database character set', self::WARN, ($charset ?: 'unknown').': utf8mb4 is recommended. Tables are created as utf8mb4 anyway.', [
                    'cpanel' => 'Create the database in cPanel > MySQL Databases; new databases default to utf8mb4.',
                    'plesk' => 'In Plesk > Databases, set the database collation to utf8mb4_unicode_ci.',
                    'directadmin' => 'In DirectAdmin > MySQL Management, recreate the database with utf8mb4.',
                ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function checkDatabaseVersion(string $version): array
    {
        $isMariaDb = stripos($version, 'mariadb') !== false;
        preg_match('/(\d+\.\d+\.\d+)/', $version, $matches);
        $number = isset($matches[1]) ? $matches[1] : '0.0.0';
        $minimum = $isMariaDb ? $this->options['mariadb'] : $this->options['mysql'];
        $name = $isMariaDb ? 'MariaDB' : 'MySQL';
        $ok = version_compare($number, $minimum, '>=');

        return $this->item(
            'db_version',
            'Database version',
            $ok ? self::PASS : self::FAIL,
            $ok ? "{$name} {$number}" : "{$name} {$number} is too old: {$name} {$minimum} or newer is required (MySQL 8.0+ or MariaDB 10.6+).",
            $ok ? [] : [
                'cpanel' => 'Ask your host which MySQL or MariaDB version your plan runs and whether a newer one is available.',
                'plesk' => 'In Plesk > Tools & Settings > Database Servers, check the server version, or ask your host.',
                'directadmin' => 'Ask your host to upgrade MySQL or MariaDB (DirectAdmin CustomBuild).',
            ],
        );
    }

    /**
     * Overall result: fail if any item fails, otherwise warn if any warns.
     *
     * @param  list<array<string, mixed>>  $items
     */
    public static function summary(array $items): string
    {
        $statuses = array_column($items, 'status');

        if (in_array(self::FAIL, $statuses, true)) {
            return self::FAIL;
        }

        return in_array(self::WARN, $statuses, true) ? self::WARN : self::PASS;
    }

    /**
     * @return array<string, mixed>
     */
    private function checkPhpVersion(): array
    {
        $minimum = $this->options['php'];
        $ok = version_compare(PHP_VERSION, $minimum, '>=');

        return $this->item('php', 'PHP version', $ok ? self::PASS : self::FAIL, $ok ? PHP_VERSION : PHP_VERSION." is too old: PHP {$minimum} or newer is required.", $ok ? [] : [
            'cpanel' => 'cPanel > MultiPHP Manager (or Select PHP Version): choose PHP 8.3 for this domain.',
            'plesk' => 'Plesk > Websites & Domains > PHP: choose PHP 8.3.',
            'directadmin' => 'DirectAdmin > Domain Setup > PHP Version Selector: choose PHP 8.3.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function checkExtension(string $extension): array
    {
        $ok = extension_loaded($extension);

        return $this->item("ext_{$extension}", "PHP extension: {$extension}", $ok ? self::PASS : self::FAIL, $ok ? 'Loaded' : 'Missing', $ok ? [] : [
            'cpanel' => "cPanel > Select PHP Version > Extensions: tick {$extension}.",
            'plesk' => "Plesk > PHP Settings: enable {$extension}, or ask your host.",
            'directadmin' => "DirectAdmin > PHP Version Selector > Extensions: enable {$extension}.",
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function checkDisabledFunctions(): array
    {
        $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
        $required = ['fopen', 'file_put_contents', 'rename', 'tempnam', 'json_encode', 'random_bytes'];
        $blocking = array_values(array_intersect($required, $disabled));

        if ($blocking !== []) {
            return $this->item('functions', 'Required PHP functions', self::FAIL, 'Disabled: '.implode(', ', $blocking), [
                'cpanel' => 'cPanel > MultiPHP INI Editor: remove these from disable_functions, or ask your host.',
                'plesk' => 'Plesk > PHP Settings > disable_functions: remove these.',
                'directadmin' => 'Ask your host to remove these from disable_functions.',
            ]);
        }

        $optional = array_values(array_intersect(['exec', 'proc_open', 'symlink'], $disabled));

        return $this->item('functions', 'Required PHP functions', self::PASS, $optional === []
            ? 'Available'
            : 'Available ('.implode(', ', $optional).' disabled, which is fine)');
    }

    /**
     * @return array<string, mixed>
     */
    private function checkWritable(string $label, string $path): array
    {
        $ok = is_dir($path) ? is_writable($path) : is_writable(dirname($path));

        return $this->item('writable_'.preg_replace('/[^a-z0-9]+/', '_', strtolower($label)), "Writable: {$label}", $ok ? self::PASS : self::FAIL, $ok ? 'Writable' : "{$path} is not writable by PHP.", $ok ? [] : [
            'cpanel' => 'cPanel > File Manager: right-click the folder > Change Permissions > 755 (or 775).',
            'plesk' => 'Plesk > Files: select the folder > Change Permissions, allow write for the owner.',
            'directadmin' => 'DirectAdmin > File Manager: set the folder permissions to 755.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function checkMemory(): array
    {
        $bytes = self::toBytes((string) ini_get('memory_limit'));
        $hints = [
            'cpanel' => 'cPanel > MultiPHP INI Editor: set memory_limit to 256M.',
            'plesk' => 'Plesk > PHP Settings: set memory_limit to 256M.',
            'directadmin' => 'DirectAdmin > PHP Settings (or ask your host): set memory_limit to 256M.',
        ];

        if ($bytes < 0 || $bytes >= 256 * 1024 * 1024) {
            return $this->item('memory', 'Memory limit', self::PASS, (string) ini_get('memory_limit'));
        }

        return $this->item('memory', 'Memory limit', $bytes >= 128 * 1024 * 1024 ? self::WARN : self::FAIL, ini_get('memory_limit').': 256M is recommended.', $hints);
    }

    /**
     * @return array<string, mixed>
     */
    private function checkExecutionTime(): array
    {
        $seconds = (int) ini_get('max_execution_time');
        $ok = $seconds === 0 || $seconds >= 30;

        return $this->item('execution_time', 'Maximum execution time', $ok ? self::PASS : self::WARN, $seconds === 0 ? 'Unlimited' : "{$seconds} seconds".($ok ? '' : ': 30 seconds or more is recommended.'), $ok ? [] : [
            'cpanel' => 'cPanel > MultiPHP INI Editor: set max_execution_time to 30 or more.',
            'plesk' => 'Plesk > PHP Settings: set max_execution_time to 30 or more.',
            'directadmin' => 'Ask your host to raise max_execution_time to 30 or more.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function checkDisk(): array
    {
        $path = $this->options['base_path'] ?: getcwd();
        $free = function_exists('disk_free_space') ? @disk_free_space((string) $path) : false;

        if ($free === false) {
            return $this->item('disk', 'Free disk space', self::WARN, 'Could not be measured. About 500 MB is needed for updates and backups.');
        }

        $mb = (int) floor($free / 1024 / 1024);

        return $this->item('disk', 'Free disk space', $mb >= 500 ? self::PASS : self::WARN, "{$mb} MB free".($mb >= 500 ? '' : ': about 500 MB is needed for updates and backups.'), $mb >= 500 ? [] : [
            'cpanel' => 'cPanel > Disk Usage: remove old files or ask your host for more space.',
            'plesk' => 'Plesk > Statistics: check disk usage, or ask your host for more space.',
            'directadmin' => 'DirectAdmin > Disk Usage: remove old files or ask your host for more space.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function checkHttps(): array
    {
        $url = (string) $this->options['app_url'];

        if ($url === '') {
            return $this->item('https', 'HTTPS', self::WARN, 'The site address is not set yet.');
        }

        $ok = stripos($url, 'https://') === 0;

        return $this->item('https', 'HTTPS', $ok ? self::PASS : self::WARN, $ok ? $url : "{$url} is not HTTPS. Use HTTPS for any site with real data.", $ok ? [] : [
            'cpanel' => "cPanel > SSL/TLS Status: run AutoSSL (Let's Encrypt) for the domain.",
            'plesk' => "Plesk > SSL/TLS Certificates: install a free Let's Encrypt certificate.",
            'directadmin' => "DirectAdmin > SSL Certificates: request a free Let's Encrypt certificate.",
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function checkRewrite(): array
    {
        if (function_exists('apache_get_modules')) {
            $ok = in_array('mod_rewrite', apache_get_modules(), true);

            return $this->item('rewrite', 'URL rewriting', $ok ? self::PASS : self::FAIL, $ok ? 'mod_rewrite enabled' : 'mod_rewrite is not enabled.', $ok ? [] : [
                'cpanel' => 'Ask your host to enable mod_rewrite (it is on for almost every cPanel host).',
                'plesk' => 'Plesk > Apache & nginx Settings: make sure .htaccess files are allowed.',
                'directadmin' => 'Ask your host to enable mod_rewrite.',
            ]);
        }

        return $this->item('rewrite', 'URL rewriting', self::PASS, 'Not detectable here; the install wizard loading at /install proves it works.');
    }

    /**
     * @return array<string, mixed>
     */
    private function checkOutbound(): array
    {
        if (! $this->options['outbound']) {
            return $this->item('outbound', 'Outbound HTTPS', self::PASS, 'Not checked');
        }

        $host = (string) $this->options['feed_host'];
        $socket = @fsockopen('ssl://'.$host, 443, $errno, $error, 5);

        if ($socket === false) {
            return $this->item('outbound', 'Outbound HTTPS', self::WARN, "Could not reach {$host}: update checks and downloads will need a manual upload.", [
                'cpanel' => 'Ask your host to allow outbound HTTPS (port 443) from PHP.',
                'plesk' => 'Ask your host to allow outbound HTTPS (port 443) from PHP.',
                'directadmin' => 'Ask your host to allow outbound HTTPS (port 443) from PHP.',
            ]);
        }

        fclose($socket);

        return $this->item('outbound', 'Outbound HTTPS', self::PASS, "Reached {$host}");
    }

    /**
     * @param  array<string, string>  $hints
     * @return array<string, mixed>
     */
    private function item(string $key, string $label, string $status, string $detail, array $hints = []): array
    {
        return ['key' => $key, 'label' => $label, 'status' => $status, 'detail' => $detail, 'hints' => $hints];
    }

    public static function toBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return -1;
        }

        $number = (int) $value;
        $unit = strtolower(substr($value, -1));

        if ($unit === 'g') {
            return $number * 1024 * 1024 * 1024;
        }

        if ($unit === 'm') {
            return $number * 1024 * 1024;
        }

        if ($unit === 'k') {
            return $number * 1024;
        }

        return $number;
    }
}
