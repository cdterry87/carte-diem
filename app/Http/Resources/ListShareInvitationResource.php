<?php

namespace App\Http\Resources;

use App\Models\ListShareInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ListShareInvitation
 */
class ListShareInvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'email' => $this->email,
            'role' => $this->role->value,
            'expires_at' => $this->expires_at->toIso8601String(),
            'list_title' => $this->whenLoaded('shoppingList', fn () => $this->shoppingList->title),
            'list' => $this->whenLoaded('shoppingList', fn () => $this->shoppingList->uuid),
        ];
    }
}
