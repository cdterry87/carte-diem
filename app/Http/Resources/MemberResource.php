<?php

namespace App\Http\Resources;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class MemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Membership $membership */
        $membership = $this->resource->getRelation('pivot');

        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $membership->role->value,
            'joined_at' => $membership->joined_at->toIso8601String(),
        ];
    }
}
