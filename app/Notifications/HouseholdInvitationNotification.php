<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class HouseholdInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $householdName,
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
            ->subject(__(':name invited you to :household on :app', ['name' => $this->inviterName, 'household' => $this->householdName, 'app' => config('app.name')]))
            ->line(__(':name invited you to join :household.', ['name' => $this->inviterName, 'household' => $this->householdName]))
            ->action(__('View invitation'), route('invitations.show', $this->token))
            ->line(__('Sign in or register with this email address to respond. This invitation expires in 14 days.'));
    }
}
