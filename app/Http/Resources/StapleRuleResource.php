<?php

namespace App\Http\Resources;

use App\Models\StapleRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StapleRule
 */
class StapleRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->product->canonical_name,
            'product' => $this->product->uuid,
            'cadence_days' => $this->cadence_days,
            'prediction_enabled' => $this->prediction_enabled,
            'reminder_enabled' => $this->reminder_enabled,
            'snoozed_until' => $this->snoozed_until?->toIso8601String(),
        ];
    }
}
