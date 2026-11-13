<?php

namespace App\Actions\Recommendations;

use App\Exceptions\DomainConflictException;
use App\Models\StapleRule;
use App\Services\ProductResolver;
use Illuminate\Database\Eloquent\Model;

class SaveStapleRule
{
    public function __construct(private readonly ProductResolver $products) {}

    /**
     * @param  array{cadence_days?: int|null, prediction_enabled?: bool, reminder_enabled?: bool}  $attributes
     */
    public function create(Model $owner, string $name, array $attributes): StapleRule
    {
        $product = $this->products->resolve($owner, $name);

        $exists = StapleRule::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->where('product_id', $product->id)
            ->exists();

        if ($exists) {
            throw new DomainConflictException('That item is already a staple.', 'duplicate_staple');
        }

        return StapleRule::query()->create([
            ...$attributes,
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
            'product_id' => $product->id,
        ])->load('product');
    }
}
