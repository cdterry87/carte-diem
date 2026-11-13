<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Notifications\Concerns\RespectsPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TripCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable, RespectsPreferences;

    public function __construct(public readonly string $listTitle, public readonly string $summary, public readonly string $url) {}

    public function notificationType(): NotificationType
    {
        return NotificationType::TripCompleted;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Trip finished: :list', ['list' => $this->listTitle]))
            ->line($this->summary)
            ->action(__('View trip'), url($this->url));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->notificationType()->value,
            'title' => __('Trip finished: :list', ['list' => $this->listTitle]),
            'body' => $this->summary,
            'url' => $this->url,
        ];
    }
}
