<?php

namespace App\Services;

use Carbon\CarbonImmutable;

final readonly class ProductPurchaseStat
{
    /**
     * @param  array<int, int>  $transactionIds  Newest first.
     */
    public function __construct(
        public int $productId,
        public array $transactionIds,
        public CarbonImmutable $lastPurchasedAt,
        public CarbonImmutable $lastRecordedAt,
        public int $recentEvents,
        public ?float $medianIntervalDays,
    ) {}

    public function daysSinceLastPurchase(): int
    {
        return (int) floor($this->lastPurchasedAt->diffInDays(now(), true));
    }
}
