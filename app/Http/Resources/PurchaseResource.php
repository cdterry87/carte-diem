<?php

namespace App\Http\Resources;

use App\Models\PurchaseTransaction;
use App\Models\PurchaseTransactionItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * @mixin PurchaseTransaction
 */
class PurchaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        return [
            'uuid' => $this->uuid,
            'owner' => $this->owner !== null ? OwnerResource::make($this->owner) : null,
            'merchant' => $this->merchant_name_snapshot,
            'purchased_at' => $this->purchased_at->toIso8601String(),
            'purchased_on' => $this->purchased_at->setTimezone($user->timezone ?? 'UTC')->toDateString(),
            'currency_code' => $this->currency_code,
            'subtotal_minor' => $this->subtotal_minor,
            'tax_minor' => $this->tax_minor,
            'discount_minor' => $this->discount_minor,
            'total_minor' => $this->total_minor,
            'entry_method' => $this->entry_method->value,
            'verification_status' => $this->verification_status->value,
            'revision' => $this->revision,
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'trip' => $this->whenLoaded('trip', fn () => $this->trip?->uuid),
            'item_count' => $this->whenCounted('items'),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (PurchaseTransactionItem $item): array => [
                'uuid' => $item->uuid,
                'description' => $item->description_snapshot,
                'quantity' => $item->quantity,
                'unit' => $item->unit_code,
                'category' => $item->category_name_snapshot,
                'category_uuid' => $item->relationLoaded('category') ? $item->category?->uuid : null,
                'line_total_minor' => $item->line_total_minor,
            ])),
            'can' => $this->whenLoaded('items', fn () => [
                'delete' => $user !== null && Gate::forUser($user)->allows('delete', $this->resource),
                'move' => $user !== null && $this->shopping_trip_id === null && Gate::forUser($user)->allows('move', $this->resource),
            ]),
        ];
    }
}
