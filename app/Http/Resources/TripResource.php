<?php

namespace App\Http\Resources;

use App\Enums\ListItemStatus;
use App\Models\PurchaseTransaction;
use App\Models\ShoppingListItem;
use App\Models\ShoppingTrip;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * @mixin ShoppingTrip
 */
class TripResource extends JsonResource
{
    public const SHOW_AMOUNTS = 'trip_amounts_visible';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();
        $list = $this->shoppingList;
        $canManageAmounts = $user !== null && Gate::forUser($user)->allows('manageAmounts', $this->resource);
        $request->attributes->set(self::SHOW_AMOUNTS, $canManageAmounts);

        return [
            'uuid' => $this->uuid,
            'status' => $this->status->value,
            'revision' => $this->revision,
            'list' => [
                'uuid' => $list->uuid,
                'title' => $list->title,
                'owner' => $list->owner !== null ? OwnerResource::make($list->owner) : null,
                'deleted' => $list->deleted_at !== null,
            ],
            'store' => StoreResource::make($this->whenLoaded('store')),
            'total_minor' => $this->when($canManageAmounts, fn () => $this->total_minor),
            'currency_code' => $this->when($canManageAmounts && $list->owner !== null, fn () => PurchaseTransaction::currencyFor($list->owner)),
            'started_at' => $this->started_at->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'started_by' => $this->whenLoaded('starter', fn () => $this->starter?->name),
            'completed_by' => $this->whenLoaded('completer', fn () => $this->completer?->name),
            'item_count' => $this->whenCounted('items'),
            'purchased_count' => $this->whenCounted('purchased_count'),
            'items' => TripItemResource::collection($this->whenLoaded('items')),
            'added_since' => $this->when(
                $this->relationLoaded('items') && $this->isActive(),
                fn () => $list->items()
                    ->where('status', ListItemStatus::Needed)
                    ->whereNotIn('id', $this->items->pluck('source_list_item_id')->filter())
                    ->orderBy('sort_order')
                    ->get()
                    ->map(fn (ShoppingListItem $item): array => [
                        'uuid' => $item->uuid,
                        'name' => $item->name_snapshot,
                        'quantity' => $item->requested_quantity,
                        'unit' => $item->unit_code,
                    ]),
            ),
            'purchase' => $this->when($user !== null && $this->relationLoaded('purchase') && $this->purchase !== null && Gate::forUser($user)->allows('view', $this->purchase), fn () => $this->purchase?->uuid),
            'can' => [
                'run' => $user !== null && $this->isActive() && Gate::forUser($user)->allows('run', $this->resource),
                'manage_amounts' => $canManageAmounts,
            ],
        ];
    }
}
