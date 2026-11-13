<?php

namespace App\Services;

use App\Models\Product;

final readonly class Recommendation
{
    public function __construct(
        public Product $product,
        public string $kind,
        public string $reason,
        public float $score,
    ) {}
}
