<?php

namespace App\Actions\Trips;

use App\Enums\ListItemStatus;
use App\Enums\ListStatus;
use App\Enums\TripStatus;
use App\Exceptions\DomainConflictException;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\ShoppingTrip;
use App\Models\ShoppingTripItem;
use App\Models\Store;
use App\Models\User;
use App\Services\StoreCategoryOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartShoppingTrip
{
    public function __construct(private readonly StoreCategoryOrder $order) {}

    /**
     * @param  list<string>|null  $itemUuids  Limit the snapshot to these needed items.
     */
    public function handle(ShoppingList $list, User $user, ?Store $store = null, ?array $itemUuids = null, bool $noStore = false): ShoppingTrip
    {
        return DB::transaction(function () use ($list, $user, $store, $itemUuids, $noStore): ShoppingTrip {
            /** @var ShoppingList $locked */
            $locked = ShoppingList::query()->whereKey($list->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== ListStatus::Active) {
                throw new DomainConflictException('Unarchive this list before starting a trip.', 'list_archived');
            }

            $active = ShoppingTrip::query()->where('shopping_list_id', $locked->id)->where('status', TripStatus::Active)->first();

            if ($active !== null) {
                throw new DomainConflictException('This list already has an active trip.', 'active_trip', ['trip' => $active->uuid]);
            }

            $items = $locked->items()
                ->with('category')
                ->where('status', ListItemStatus::Needed)
                ->when($itemUuids !== null, fn ($query) => $query->whereIn('uuid', $itemUuids ?? []))
                ->orderBy('sort_order')
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['items' => __('Add at least one needed item before starting a trip.')]);
            }

            $trip = ShoppingTrip::query()->create([
                'shopping_list_id' => $locked->id,
                'store_id' => $noStore ? null : ($store->id ?? $locked->default_store_id),
                'started_by' => $user->id,
                'started_at' => now(),
            ]);

            $positions = $this->order->positions($trip->store);

            $items->each(function (ShoppingListItem $item) use ($trip, $positions): void {
                $sort = $this->order->sortValue($positions, $item->category_id, $item->category->sort_order ?? 0);
                $trip->items()->create(ShoppingTripItem::snapshotAttributes($item, $sort));
            });

            return $trip;
        });
    }
}
