<?php

namespace App\Http\Resources;

use App\Models\Household;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Model $resource
 */
class OwnerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $owner = $this->resource;

        return [
            'type' => $owner->getMorphClass(),
            'uuid' => $owner instanceof User || $owner instanceof Household ? $owner->uuid : null,
            'name' => $owner instanceof Household ? $owner->name : __('Personal'),
        ];
    }
}
