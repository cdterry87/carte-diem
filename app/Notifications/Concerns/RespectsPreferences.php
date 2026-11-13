<?php

namespace App\Notifications\Concerns;

use App\Enums\NotificationType;
use App\Models\User;
use App\Services\NotificationChannels;

trait RespectsPreferences
{
    abstract public function notificationType(): NotificationType;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return [];
        }

        $channels = app(NotificationChannels::class)->for($notifiable, $this->notificationType());

        return in_array('database', $channels, true) ? [...$channels, 'broadcast'] : $channels;
    }
}
