<?php

namespace App\Listeners;

use App\Events\TripCompleted;
use App\Models\User;
use App\Notifications\TripCompletedNotification;
use App\Services\ScopeAudience;
use Illuminate\Support\Facades\Notification;

class NotifyTripCompleted
{
    public function __construct(private readonly ScopeAudience $audience) {}

    public function handle(TripCompleted $event): void
    {
        $trip = $event->trip;
        $list = $trip->shoppingList;
        $count = $trip->items()->where('purchased', true)->count();
        $actor = $trip->completer->name ?? __('Someone');

        $recipients = $this->audience->forList($list)
            ->reject(fn (User $user): bool => $user->id === $trip->completed_by);

        Notification::send($recipients, new TripCompletedNotification(
            $list->title,
            trans_choice(':actor finished a trip and bought :count item.|:actor finished a trip and bought :count items.', $count, ['actor' => $actor, 'count' => $count]),
            '/trips/'.$trip->uuid,
        ));
    }
}
