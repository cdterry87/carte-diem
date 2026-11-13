<?php

namespace App\Actions\Trips;

use App\Enums\TripStatus;
use App\Events\TripItemChecked;
use App\Exceptions\DomainConflictException;
use App\Models\ShoppingListItem;
use App\Models\ShoppingTrip;
use App\Models\ShoppingTripItem;
use App\Models\User;
use App\Services\StoreCategoryOrder;
use Illuminate\Support\Facades\DB;

class UpdateTripItem
{
    public function __construct(private readonly StoreCategoryOrder $order) {}

    /**
     * @param  array{purchased?: bool, actual_quantity?: string|float|int|null, line_total_minor?: int|null, notes?: string|null}  $attributes
     */
    public function handle(ShoppingTripItem $item, User $user, array $attributes, ?int $expectedRevision = null): ShoppingTripItem
    {
        return DB::transaction(function () use ($item, $user, $attributes, $expectedRevision): ShoppingTripItem {
            $this->assertActive($item->shopping_trip_id);

            /** @var ShoppingTripItem $locked */
            $locked = ShoppingTripItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();

            if ($expectedRevision !== null && $locked->revision !== $expectedRevision) {
                throw new DomainConflictException('This item changed since you loaded it.', 'stale_revision', ['revision' => $locked->revision]);
            }

            $locked->fill($attributes);

            if ($locked->isDirty('purchased')) {
                $locked->checked_by = $locked->purchased ? $user->id : null;
                $locked->checked_at = $locked->purchased ? now() : null;
            }

            if ($locked->isDirty()) {
                $locked->revision++;
                $locked->save();
                $this->announce($locked->shopping_trip_id, $locked->uuid, $locked->revision);
            }

            return $locked;
        });
    }

    /**
     * Bring a list item added or skipped after the trip began into the trip.
     */
    public function include(ShoppingTrip $trip, ShoppingListItem $listItem): ShoppingTripItem
    {
        return DB::transaction(function () use ($trip, $listItem): ShoppingTripItem {
            $this->assertActive($trip->id);

            /** @var ShoppingTripItem $item */
            $item = $trip->items()->firstOrCreate(
                ['source_list_item_id' => $listItem->id],
                ShoppingTripItem::snapshotAttributes($listItem->loadMissing('category'), $this->order->sortValue(
                    $this->order->positions($trip->store),
                    $listItem->category_id,
                    $listItem->category->sort_order ?? 0,
                )),
            );

            $this->announce($trip->id, $item->uuid, $item->revision);

            return $item;
        });
    }

    /**
     * The total paid, carried onto the purchase when the trip completes.
     */
    public function setTotal(ShoppingTrip $trip, ?int $total): ShoppingTrip
    {
        return DB::transaction(function () use ($trip, $total): ShoppingTrip {
            $this->assertActive($trip->id);

            /** @var ShoppingTrip $locked */
            $locked = ShoppingTrip::query()->whereKey($trip->id)->lockForUpdate()->firstOrFail();
            $locked->total_minor = $total;

            if ($locked->isDirty()) {
                $locked->revision++;
                $locked->save();
            }

            return $locked;
        });
    }

    private function announce(int $tripId, string $item, int $revision): void
    {
        /** @var ShoppingTrip $trip */
        $trip = ShoppingTrip::query()->findOrFail($tripId);

        TripItemChecked::dispatch($trip->shoppingList->uuid, $item, $revision);
    }

    /**
     * Shared lock so completion, which takes a write lock on the trip, serializes with item edits.
     */
    private function assertActive(int $tripId): void
    {
        /** @var ShoppingTrip $trip */
        $trip = ShoppingTrip::query()->whereKey($tripId)->sharedLock()->firstOrFail();

        if ($trip->status !== TripStatus::Active) {
            throw new DomainConflictException('This trip has ended and can no longer change.', 'trip_ended');
        }
    }
}
