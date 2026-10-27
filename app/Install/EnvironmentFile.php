<?php

namespace App\Install;

use RuntimeException;

/**
 * Reads and updates keys in the app's .env file.
 */
final class EnvironmentFile
{
    public function path(): string
    {
        return app()->environmentFilePath();
    }

    public function exists(): bool
    {
        return is_file($this->path());
    }

    /**
     * Create .env from .env.example when it does not exist yet.
     */
    public function ensureExists(): void
    {
        if ($this->exists()) {
            return;
        }

        $example = base_path('.env.example');
        $contents = is_file($example) ? (string) file_get_contents($example) : '';

        $this->write($contents);
    }

    /**
     * Set keys, replacing existing lines (commented or not) or appending.
     *
     * @param  array<string, string|int|bool|null>  $values
     */
    public function set(array $values): void
    {
        $this->ensureExists();
        $contents = (string) file_get_contents($this->path());

        foreach ($values as $key => $value) {
            $line = $key.'='.self::format($value);
            $pattern = '/^[ \t]*#?[ \t]*'.preg_quote($key, '/').'=.*$/m';

            $contents = preg_match($pattern, $contents)
                ? (string) preg_replace($pattern, str_replace(['\\', '$'], ['\\\\', '\\$'], $line), $contents, 1)
                : rtrim($contents)."\n".$line."\n";
        }

        $this->write($contents);
    }

    public function get(string $key): ?string
    {
        if (! $this->exists()) {
            return null;
        }

        if (! preg_match('/^[ \t]*'.preg_quote($key, '/').'=(.*)$/m', (string) file_get_contents($this->path()), $matches)) {
            return null;
        }

        $value = trim($matches[1]);

        if (strlen($value) >= 2 && $value[0] === '"' && str_ends_with($value, '"')) {
            return stripcslashes(substr($value, 1, -1));
        }

        return $value;
    }

    public static function format(string|int|bool|null $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $value = (string) $value;

        if ($value === '' || preg_match('/[\s#"\'\\\\$]/', $value)) {
            return '"'.addcslashes($value, '\\"$').'"';
        }

        return $value;
    }

    private function write(string $contents): void
    {
        $path = $this->path();
        $temp = $path.'.'.bin2hex(random_bytes(4)).'.tmp';

        if (@file_put_contents($temp, $contents) === false) {
            throw new RuntimeException("Cannot write {$path}. Make its folder writable by PHP.");
        }

        // Windows refuses the rename while another process (often a virus scanner) has the file open.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            if (@rename($temp, $path)) {
                return;
            }

            usleep(100_000);
        }

        @unlink($temp);

        if (@file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new RuntimeException("Cannot write {$path}. Make its folder writable by PHP.");
        }
    }
}
