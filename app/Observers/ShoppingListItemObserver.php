<?php

namespace App\Observers;

use App\Events\ListItemAdded;
use App\Events\ListItemRemoved;
use App\Events\ListItemUpdated;
use App\Models\ListActivityEvent;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;

class ShoppingListItemObserver
{
    public function created(ShoppingListItem $item): void
    {
        $this->record($item, ListActivityEvent::ADDED, $item->added_by);
        ListItemAdded::dispatch($this->listUuid($item), $item->uuid, $item->revision);
    }

    public function deleted(ShoppingListItem $item): void
    {
        $this->record($item, ListActivityEvent::REMOVED, auth()->id());
        ListItemRemoved::dispatch($this->listUuid($item), $item->uuid, $item->revision);
    }

    public function updated(ShoppingListItem $item): void
    {
        ListItemUpdated::dispatch($this->listUuid($item), $item->uuid, $item->revision);
    }

    public function restored(ShoppingListItem $item): void
    {
        ListItemAdded::dispatch($this->listUuid($item), $item->uuid, $item->revision);
    }

    private function listUuid(ShoppingListItem $item): string
    {
        return (string) ShoppingList::withTrashed()->whereKey($item->shopping_list_id)->value('uuid');
    }

    private function record(ShoppingListItem $item, string $action, int|string|null $actorId): void
    {
        if ($actorId === null) {
            return;
        }

        ListActivityEvent::query()->create([
            'shopping_list_id' => $item->shopping_list_id,
            'actor_id' => $actorId,
            'action' => $action,
            'item_name' => $item->name_snapshot,
        ]);
    }
}
