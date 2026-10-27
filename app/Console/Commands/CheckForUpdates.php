<?php

namespace App\Console\Commands;

use App\Models\Installation;
use App\Models\User;
use App\Notifications\UpdateAvailable;
use App\Updates\FeedClient;
use App\Updates\InvalidRelease;
use App\Updates\UpdateFeed;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

class CheckForUpdates extends Command
{
    protected $signature = 'app:check-updates';

    protected $description = 'Email Super Admins once when a newer release is available on this channel (never installs anything)';

    public function handle(): int
    {
        $channel = (string) config('updates.channel');

        try {
            $feed = FeedClient::fromConfig(Installation::query()->value('installation_id'))->fetch($channel);
        } catch (InvalidRelease $e) {
            $this->warn($e->getMessage());

            return self::SUCCESS;
        }

        $current = (string) config('app.version');
        $release = UpdateFeed::latestFor($feed, $current);

        if ($release === null) {
            $this->info("Up to date ({$current}, {$channel}).");

            return self::SUCCESS;
        }

        if (! Cache::add("updates:notified:{$release['version']}", now()->toIso8601String(), now()->addYear())) {
            $this->info("Super Admins were already told about {$release['version']}.");

            return self::SUCCESS;
        }

        Notification::send(User::query()->where('is_super_admin', true)->get(), new UpdateAvailable($release, $current));
        $this->info("Told Super Admins that {$release['version']} is available.");

        return self::SUCCESS;
    }
}
