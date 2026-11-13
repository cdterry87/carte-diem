<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Statistics from verified purchases only: list items and unfinished trips never count.
 * The thresholds are provisional and need calibrating against real household usage.
 */
class PurchaseStats
{
    public const MIN_EVENTS_FOR_INTERVAL = 3;

    public const HISTORY_DAYS = 365;

    public const RECENT_DAYS = 90;

    /**
     * @return Collection<int, ProductPurchaseStat> Keyed by product id.
     */
    public function forOwner(Model $owner): Collection
    {
        return $this->events($owner)
            ->groupBy('product_id')
            ->map(function (Collection $rows, int|string $productId): ProductPurchaseStat {
                $unique = $rows->unique('transaction_id')->values();
                /** @var Collection<int, CarbonImmutable> $dates */
                $dates = $unique->map(fn (stdClass $row): CarbonImmutable => CarbonImmutable::parse($row->purchased_at));
                /** @var CarbonImmutable $last */
                $last = $dates->first();

                return new ProductPurchaseStat(
                    productId: (int) $productId,
                    transactionIds: $unique->map(fn (stdClass $row): int => (int) $row->transaction_id)->values()->all(),
                    lastPurchasedAt: $last,
                    lastRecordedAt: CarbonImmutable::parse($unique->first()->recorded_at),
                    recentEvents: $dates->filter(fn (CarbonImmutable $date): bool => $date->greaterThanOrEqualTo(now()->subDays(self::RECENT_DAYS)))->count(),
                    medianIntervalDays: $this->medianInterval($dates),
                );
            });
    }

    /**
     * Distinct verified transactions in the scope, newest first.
     *
     * @return Collection<int, int>
     */
    public function recentTransactionIds(Model $owner, int $limit): Collection
    {
        return $this->events($owner)->pluck('transaction_id')->map(fn ($id): int => (int) $id)->unique()->take($limit)->values();
    }

    /**
     * @return Collection<int, stdClass>
     */
    private function events(Model $owner): Collection
    {
        return DB::table('purchase_transaction_items as items')
            ->join('purchase_transactions as purchases', 'purchases.id', '=', 'items.purchase_transaction_id')
            ->where('purchases.owner_type', $owner->getMorphClass())
            ->where('purchases.owner_id', $owner->getKey())
            ->where('purchases.verification_status', 'verified')
            ->whereNull('purchases.deleted_at')
            ->where('items.is_verified', true)
            ->whereNotNull('items.product_id')
            ->where('purchases.purchased_at', '>=', now()->subDays(self::HISTORY_DAYS))
            ->orderByDesc('purchases.purchased_at')
            ->orderByDesc('purchases.id')
            ->get(['items.product_id', 'purchases.id as transaction_id', 'purchases.purchased_at', 'purchases.created_at as recorded_at']);
    }

    /**
     * @param  Collection<int, CarbonImmutable>  $dates  Newest first.
     */
    private function medianInterval(Collection $dates): ?float
    {
        if ($dates->count() < self::MIN_EVENTS_FOR_INTERVAL) {
            return null;
        }

        $intervals = $dates->sliding(2)
            ->map(fn (Collection $pair): float => $pair->last()->diffInDays($pair->first(), true))
            ->values();

        return (float) $intervals->median();
    }
}
