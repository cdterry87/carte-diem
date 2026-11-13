<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Database\Eloquent\Model;

/**
 * Turns free-text item entry into a name, amount, and product. An explicit quantity
 * or unit means the name is taken literally rather than parsed for an amount.
 */
class ItemEntryResolver
{
    public function __construct(
        private readonly ItemEntryParser $parser,
        private readonly ProductResolver $products,
    ) {}

    /**
     * @param  array{name: string, quantity?: string|float|int|null, unit?: string|null}  $input
     */
    public function resolve(Model $owner, array $input, ?Category $category = null): ResolvedItemEntry
    {
        if (isset($input['quantity']) || isset($input['unit'])) {
            $name = trim($input['name']);
            $quantity = isset($input['quantity']) ? (string) $input['quantity'] : null;
            $unit = $input['unit'] ?? null;
        } else {
            $parsed = $this->parser->parse($input['name']);
            $name = $parsed->name;
            $quantity = $parsed->quantity;
            $unit = $parsed->unit;
        }

        return new ResolvedItemEntry($name, $quantity, $unit, $this->products->resolve($owner, $name, $category));
    }
}
