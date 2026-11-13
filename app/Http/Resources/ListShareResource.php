<?php

namespace App\Http\Resources;

use App\Models\ListShare;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ListShare
 */
class ListShareResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => $this->user->uuid,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'role' => $this->role->value,
        ];
    }
}
