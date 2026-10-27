<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class UpdateAvailable extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $release  a feed entry
     */
    public function __construct(public readonly array $release, public readonly string $currentVersion) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(config('app.name')." {$this->release['version']} is available")
            ->greeting('An update is available')
            ->line("Version {$this->release['version']} is available on the {$this->release['channel']} channel. This site runs {$this->currentVersion}.")
            ->line(Str::limit((string) $this->release['changelog'], 600))
            ->action('Review and update', route('admin.updates.index'))
            ->line('Updates never install themselves. A Super Admin starts each one.');
    }
}
