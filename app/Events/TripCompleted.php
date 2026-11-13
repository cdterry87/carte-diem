<?php

namespace App\Events;

use App\Models\ShoppingTrip;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TripCompleted implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly ShoppingTrip $trip) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('list.'.$this->trip->shoppingList->uuid)];
    }

    public function broadcastAs(): string
    {
        return 'TripCompleted';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['trip' => $this->trip->uuid, 'revision' => $this->trip->revision];
    }
}
