<?php

namespace App\Actions\Trips;

use App\Enums\EntryMethod;
use App\Enums\TripStatus;
use App\Enums\VerificationStatus;
use App\Events\TripCompleted;
use App\Exceptions\DomainConflictException;
use App\Models\AuditLog;
use App\Models\PurchaseTransaction;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\ShoppingTrip;
use App\Models\ShoppingTripItem;
use App\Models\User;
use App\Services\TripReconciler;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CompleteShoppingTrip
{
    public function __construct(private readonly TripReconciler $reconciler) {}

    /**
     * Idempotent: repeating a completed trip returns it without writing anything.
     */
    public function handle(ShoppingTrip $trip, User $user): ShoppingTrip
    {
        return DB::transaction(function () use ($trip, $user): ShoppingTrip {
            /** @var ShoppingTrip $locked */
            $locked = ShoppingTrip::query()->whereKey($trip->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === TripStatus::Completed) {
                return $locked;
            }

            if ($locked->status === TripStatus::Cancelled) {
                throw new DomainConflictException('A cancelled trip cannot be completed.', 'trip_ended');
            }

            $list = ShoppingList::withTrashed()->whereKey($locked->shopping_list_id)->lockForUpdate()->firstOrFail();
            /** @var Collection<int, ShoppingTripItem> $purchased */
            $purchased = $locked->items()->where('purchased', true)->orderBy('sort_order')->get();

            if ($purchased->isNotEmpty()) {
                $this->recordPurchase($locked, $list, $purchased, $user);
                $this->reconcileList($purchased);
            }

            $locked->update([
                'status' => TripStatus::Completed,
                'completed_at' => now(),
                'completed_by' => $user->id,
                'revision' => $locked->revision + 1,
            ]);

            AuditLog::record($user, 'trip.completed', $locked, ['purchased_items' => $purchased->count()]);
            TripCompleted::dispatch($locked);

            return $locked;
        });
    }

    /**
     * @param  Collection<int, ShoppingTripItem>  $purchased
     */
    private function recordPurchase(ShoppingTrip $trip, ShoppingList $list, Collection $purchased, User $user): void
    {
        $owner = $list->owner;

        $transaction = PurchaseTransaction::query()->create([
            'owner_type' => $list->owner_type,
            'owner_id' => $list->owner_id,
            'shopping_trip_id' => $trip->id,
            'store_id' => $trip->store_id,
            'merchant_name_snapshot' => $trip->store?->name,
            'purchased_at' => now(),
            'currency_code' => $owner !== null ? PurchaseTransaction::currencyFor($owner) : 'USD',
            'total_minor' => $trip->total_minor,
            'entry_method' => EntryMethod::Trip,
            'verification_status' => VerificationStatus::Verified,
            'source_kind' => 'trip',
            'source_identifier' => (string) $trip->id,
            'created_by' => $user->id,
        ]);

        $purchased->each(function (ShoppingTripItem $item) use ($transaction): void {
            $transaction->items()->create([
                'source_trip_item_id' => $item->id,
                'product_id' => $item->product_id,
                'description_snapshot' => $item->name_snapshot,
                'quantity' => $item->actual_quantity ?? $item->planned_quantity,
                'unit_code' => $item->planned_unit,
                'line_total_minor' => $item->line_total_minor,
                'category_id' => $item->category_id,
                'category_name_snapshot' => $item->category_name_snapshot,
                'is_verified' => true,
            ]);
        });
    }

    /**
     * @param  Collection<int, ShoppingTripItem>  $purchased
     */
    private function reconcileList(Collection $purchased): void
    {
        $listItems = ShoppingListItem::query()
            ->whereIn('id', $purchased->pluck('source_list_item_id')->filter())
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $purchased->each(function (ShoppingTripItem $tripItem) use ($listItems): void {
            $listItem = $listItems->get($tripItem->source_list_item_id);

            if ($listItem !== null) {
                $this->reconciler->apply($tripItem, $listItem);
            }
        });
    }
}
