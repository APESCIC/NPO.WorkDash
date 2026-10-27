<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * The checks behind /up, also run by the updater before it leaves
 * maintenance mode.
 */
class HealthCheck
{
    /**
     * @return array{ok: bool, version: string, checks: array<string, array{ok: bool, detail: string}>}
     */
    public function run(): array
    {
        $checks = [
            'database' => $this->check(fn () => DB::select('select 1') !== [] ? 'Connected' : 'No result'),
            'cache' => $this->check(function () {
                $key = 'health:'.Str::random(8);
                Cache::put($key, 'ok', 10);
                $value = Cache::pull($key);

                return $value === 'ok' ? 'Read and write' : throw new RuntimeException('Cache did not return the stored value');
            }),
            'storage' => $this->check(function () {
                $file = storage_path('framework/health-'.Str::random(8));

                if (@file_put_contents($file, 'ok') === false) {
                    throw new RuntimeException(storage_path('framework').' is not writable');
                }

                @unlink($file);

                return 'Writable';
            }),
            'scheduler' => $this->check(fn () => Heartbeat::isStale()
                ? throw new RuntimeException('No scheduler heartbeat for '.Heartbeat::STALE_AFTER_MINUTES.' minutes')
                : 'Last ran '.(Heartbeat::lastSeen()?->diffForHumans() ?? 'just installed')),
            'version' => $this->check(function () {
                $file = trim((string) @file_get_contents(base_path('VERSION')));

                return $file === config('app.version') ? (string) config('app.version') : throw new RuntimeException('VERSION does not match the loaded configuration; clear the config cache');
            }),
        ];

        return [
            'ok' => collect($checks)->every(fn (array $check) => $check['ok']),
            'version' => (string) config('app.version'),
            'checks' => $checks,
        ];
    }

    /**
     * @param  callable(): string  $probe
     * @return array{ok: bool, detail: string}
     */
    private function check(callable $probe): array
    {
        try {
            return ['ok' => true, 'detail' => $probe()];
        } catch (Throwable $e) {
            return ['ok' => false, 'detail' => $e->getMessage()];
        }
    }
}
