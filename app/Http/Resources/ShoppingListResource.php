<?php

namespace App\Http\Resources;

use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * @mixin ShoppingList
 */
class ShoppingListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();
        $gate = $user === null ? null : Gate::forUser($user);

        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'owner' => $this->owner !== null ? OwnerResource::make($this->owner) : null,
            'default_store' => StoreResource::make($this->whenLoaded('defaultStore')),
            'active_trip' => $this->activeTrip()->value('uuid'),
            'is_favorite' => $user !== null && $this->favoritedBy()->whereKey($user->id)->exists(),
            'needed_count' => $this->whenCounted('neededItems'),
            'item_count' => $this->whenCounted('items'),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'source_list' => $this->source_list_id !== null,
            'can' => [
                'update' => $gate?->allows('update', $this->resource) ?? false,
                'edit_items' => $gate?->allows('editItems', $this->resource) ?? false,
                'run_trip' => $gate?->allows('runTrip', $this->resource) ?? false,
                'share' => $gate?->allows('share', $this->resource) ?? false,
                'delete' => $gate?->allows('delete', $this->resource) ?? false,
                'restore' => $gate?->allows('restore', $this->resource) ?? false,
                'leave' => $user !== null && $this->shareRoleFor($user) !== null,
            ],
        ];
    }
}
