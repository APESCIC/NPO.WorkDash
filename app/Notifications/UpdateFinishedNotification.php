<?php

namespace App\Notifications;

use App\Models\UpdateRun;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UpdateFinishedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly UpdateRun $run) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $run = $this->run;
        $succeeded = $run->status === UpdateRun::SUCCEEDED;
        $outcome = match ($run->status) {
            UpdateRun::SUCCEEDED => 'succeeded',
            UpdateRun::ROLLED_BACK => 'failed and was rolled back',
            default => 'failed',
        };

        $message = (new MailMessage)
            ->subject(config('app.name')." update to {$run->to_version} {$outcome}")
            ->greeting($succeeded ? 'Update complete' : 'Update failed')
            ->line("The update from {$run->from_version} to {$run->to_version} {$outcome}.")
            ->line('Started from the '.($run->source === 'cli' ? 'command line' : 'web').($run->starter ? " by {$run->starter->name}" : '').($run->durationSeconds() !== null ? ", took {$run->durationSeconds()} seconds." : '.'));

        if (! $succeeded) {
            $message->error()->line("Reason: {$run->error}");
        }

        return $message->action('Read the update log', route('admin.updates.log', $run));
    }
}
