<?php

namespace App\Http\Resources;

use App\Models\ShoppingListItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ShoppingListItem
 */
class ListItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name_snapshot,
            'quantity' => $this->requested_quantity,
            'unit' => $this->unit_code,
            'notes' => $this->notes,
            'status' => $this->status->value,
            'needs_review' => $this->needs_review,
            'sort_order' => $this->sort_order,
            'revision' => $this->revision,
            'category' => CategoryResource::make($this->whenLoaded('category')),
            'product' => $this->whenLoaded('product', fn () => $this->product?->uuid),
            'added_by' => $this->whenLoaded('addedBy', fn () => $this->addedBy?->name),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
