<?php

namespace App\Http\Resources;

use App\Models\Household;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Household
 */
class HouseholdResource extends JsonResource
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
            'name' => $this->name,
            'timezone' => $this->timezone,
            'currency_code' => $this->currency_code,
            'role' => $user?->roleIn($this->resource)?->value,
            'member_count' => $this->whenCounted('members'),
        ];
    }
}
