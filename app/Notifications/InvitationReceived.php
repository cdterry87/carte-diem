<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\User;
use App\Services\NotificationChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * The in-app companion to an invitation email, for people who already have an account.
 */
class InvitationReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $title) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User && app(NotificationChannels::class)->preference($notifiable, NotificationType::Invitation)['in_app']
            ? ['database', 'broadcast']
            : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => NotificationType::Invitation->value,
            'title' => $this->title,
            'body' => __('Open your dashboard to accept or decline.'),
            'url' => '/dashboard',
        ];
    }
}
