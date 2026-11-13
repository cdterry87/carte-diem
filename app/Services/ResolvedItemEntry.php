<?php

namespace App\Services;

use App\Models\Product;

final readonly class ResolvedItemEntry
{
    public function __construct(
        public string $name,
        public ?string $quantity,
        public ?string $unit,
        public Product $product,
    ) {}
}
