<?php

namespace App\Support;

use App\Models\Installation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Throwable;

/**
 * When the scheduler last ran, recorded every minute by app:heartbeat.
 */
final class Heartbeat
{
    public const KEY = 'scheduler:heartbeat';

    public const STALE_AFTER_MINUTES = 10;

    public static function record(): void
    {
        Cache::forever(self::KEY, now()->getTimestamp());
    }

    public static function lastSeen(): ?CarbonImmutable
    {
        try {
            $timestamp = Cache::get(self::KEY);
        } catch (Throwable) {
            return null;
        }

        return is_numeric($timestamp) ? CarbonImmutable::createFromTimestamp((int) $timestamp, (string) config('app.timezone')) : null;
    }

    /**
     * Stale when the scheduler has not run for 10 minutes. A fresh install
     * gets the same grace period to set up its cron job.
     */
    public static function isStale(): bool
    {
        $threshold = Date::now()->subMinutes(self::STALE_AFTER_MINUTES);
        $lastSeen = self::lastSeen();

        if ($lastSeen !== null && $lastSeen->greaterThan($threshold)) {
            return false;
        }

        try {
            $installedAt = Installation::query()->value('installed_at');
        } catch (Throwable) {
            $installedAt = null;
        }

        return $installedAt === null || Date::parse($installedAt)->lessThanOrEqualTo($threshold);
    }
}
