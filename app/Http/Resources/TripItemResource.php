<?php

namespace App\Http\Resources;

use App\Models\ShoppingTripItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ShoppingTripItem
 */
class TripItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name_snapshot,
            'category' => $this->category_name_snapshot,
            'category_sort' => $this->category_sort_snapshot,
            'planned_quantity' => $this->planned_quantity,
            'planned_unit' => $this->planned_unit,
            'actual_quantity' => $this->actual_quantity,
            // TripResource decides once per request, and direct responses opt in. The default hides it from guests.
            'line_total_minor' => $this->when($request->attributes->getBoolean(TripResource::SHOW_AMOUNTS), fn () => $this->line_total_minor),
            'notes' => $this->notes,
            'purchased' => $this->purchased,
            'checked_by' => $this->whenLoaded('checker', fn () => $this->checker?->name),
            'checked_at' => $this->checked_at?->toIso8601String(),
            'sort_order' => $this->sort_order,
            'revision' => $this->revision,
        ];
    }
}
