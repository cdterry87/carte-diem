<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ListShareInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $listTitle,
        public readonly string $inviterName,
        public readonly string $token,
    ) {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__(':name shared a shopping list with you', ['name' => $this->inviterName]))
            ->line(__(':name shared the list ":list" with you.', ['name' => $this->inviterName, 'list' => $this->listTitle]))
            ->action(__('View invitation'), route('list-invitations.show', $this->token))
            ->line(__('Sign in or register with this email address to respond. This invitation expires in 14 days.'));
    }
}
