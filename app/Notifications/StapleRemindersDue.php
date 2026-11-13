<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Notifications\Concerns\RespectsPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StapleRemindersDue extends Notification implements ShouldQueue
{
    use Queueable, RespectsPreferences;

    /**
     * @param  array<int, string>  $names
     */
    public function __construct(public readonly string $ownerName, public readonly array $names, public readonly string $url) {}

    public function notificationType(): NotificationType
    {
        return NotificationType::ReminderDue;
    }

    /**
     * Worded as a possibility: we know purchase timing, not what is on the shelf.
     */
    private function body(): string
    {
        return __('You may need: :items.', ['items' => implode(', ', $this->names)]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Items you may need soon'))
            ->line(__('Based on your staples for :owner.', ['owner' => $this->ownerName]))
            ->line($this->body())
            ->action(__('Review staples'), url($this->url));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->notificationType()->value,
            'title' => __('Items you may need soon'),
            'body' => $this->body(),
            'url' => $this->url,
        ];
    }
}
