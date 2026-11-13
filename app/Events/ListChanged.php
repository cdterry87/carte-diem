<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A hint that a list changed. It carries identifiers only: clients refetch through
 * authorized requests, so the database stays authoritative and no content is broadcast.
 */
abstract class ListChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * Broadcasts are advisory, so a failed one is not worth retrying.
     */
    public int $tries = 1;

    public function __construct(
        public readonly string $list,
        public readonly ?string $item = null,
        public readonly ?int $revision = null,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('list.'.$this->list)];
    }

    public function broadcastAs(): string
    {
        return class_basename($this);
    }
}
