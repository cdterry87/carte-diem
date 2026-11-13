<?php

namespace App\Services;

final readonly class ParsedItem
{
    public function __construct(
        public string $name,
        public ?string $quantity = null,
        public ?string $unit = null,
    ) {}
}
