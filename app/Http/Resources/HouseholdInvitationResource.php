<?php

namespace App\Http\Resources;

use App\Models\HouseholdInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin HouseholdInvitation
 */
class HouseholdInvitationResource extends JsonResource
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
            'household_name' => $this->whenLoaded('household', fn () => $this->household->name),
            'household' => $this->whenLoaded('household', fn () => $this->household->uuid),
        ];
    }
}
