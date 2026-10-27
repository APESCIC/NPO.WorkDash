<?php

namespace App\Listeners;

use App\Models\User;
use App\Notifications\UpdateFinishedNotification;
use App\Updates\Events\UpdateFinished;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class SendUpdateFinishedNotification
{
    public function handle(UpdateFinished $event): void
    {
        $admins = User::query()->where('is_super_admin', true)->get();

        try {
            Notification::send($admins, new UpdateFinishedNotification($event->run));
        } catch (Throwable $e) {
            // A broken mail setup must not turn a finished update into an error.
            Log::warning('Could not send the update notification', ['run' => $event->run->id, 'error' => $e->getMessage()]);
        }
    }
}
