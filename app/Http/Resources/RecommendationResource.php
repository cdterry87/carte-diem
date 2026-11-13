<?php

namespace App\Http\Resources;

use App\Models\Household;
use App\Models\User;
use App\Services\Recommendation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Recommendation $resource
 */
class RecommendationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $product = $this->resource->product;
        $owner = $product->scope;

        return [
            'product' => $product->uuid,
            'name' => $product->canonical_name,
            'category' => $product->defaultCategory?->name,
            'kind' => $this->resource->kind,
            'reason' => $this->resource->reason,
            'owner' => $owner instanceof User || $owner instanceof Household ? OwnerResource::make($owner) : null,
        ];
    }
}
