<?php

namespace App\Services;

use App\Enums\ListItemStatus;
use App\Enums\ListStatus;
use App\Models\Product;
use App\Models\RecommendationDismissal;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\StapleRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Explainable, rule-based suggestions. Weights and thresholds are provisional.
 * They suggest what someone may need; they never claim anything about real stock.
 */
class Recommendations
{
    private const RECENT_TRIP_WINDOW = 7;

    private const MIN_FREQUENT_TRIPS = 2;

    private const MIN_SHARED_TRIPS = 3;

    private const RECENT_FALLBACK_LIMIT = 5;

    public function __construct(
        private readonly PurchaseStats $stats,
        private readonly StapleSchedule $schedule,
    ) {}

    /**
     * @return Collection<int, Recommendation>
     */
    public function forOwner(Model $owner, User $user, ?ShoppingList $list = null, int $limit = 10): Collection
    {
        $stats = $this->stats->forOwner($owner);
        $recentTrips = $this->stats->recentTransactionIds($owner, self::RECENT_TRIP_WINDOW);
        $rules = StapleRule::query()->where('owner_type', $owner->getMorphClass())->where('owner_id', $owner->getKey())->get()->keyBy('product_id');
        $hidden = $this->neededProductIds($owner)->merge($this->dismissedProductIds($user))->unique();

        /** @var array<int, array{kind: string, reason: string, score: float}> $candidates */
        $candidates = [];

        foreach ($stats as $productId => $stat) {
            $candidate = $this->scoreFromHistory($stat, $rules->get($productId), $recentTrips);

            if ($candidate !== null) {
                $candidates[$productId] = $candidate;
            }
        }

        foreach ($rules as $productId => $rule) {
            if (! isset($candidates[$productId]) && $this->schedule->isDue($rule, $stats->get($productId))) {
                $candidates[$productId] = [
                    'kind' => 'staple',
                    'reason' => $rule->cadence_days !== null
                        ? __('On your staples, every :days days.', ['days' => $rule->cadence_days])
                        : __('On your staples.'),
                    'score' => 0.6,
                ];
            }
        }

        foreach ($this->coPurchased($stats, $list) as $productId => $reason) {
            if (! isset($candidates[$productId])) {
                $candidates[$productId] = ['kind' => 'co_purchase', 'reason' => $reason, 'score' => 0.3];
            }
        }

        foreach ($rules as $productId => $rule) {
            if ($rule->isSnoozed()) {
                unset($candidates[$productId]);
            }
        }

        $candidates = array_diff_key($candidates, $hidden->flip()->all());

        if ($candidates === []) {
            $candidates = $this->recentFallback($owner, $hidden, $stats->isEmpty());
        }

        $products = Product::query()->with(['defaultCategory', 'scope'])->whereKey(array_keys($candidates))->get()->keyBy('id');

        return collect($candidates)
            ->map(function (array $candidate, int $productId) use ($products): ?Recommendation {
                $product = $products->get($productId);

                return $product === null ? null : new Recommendation($product, $candidate['kind'], $candidate['reason'], $candidate['score']);
            })
            ->filter()
            ->sortByDesc(fn (Recommendation $recommendation): float => $recommendation->score)
            ->take($limit)
            ->values();
    }

    /**
     * @param  Collection<int, int>  $recentTrips
     * @return array{kind: string, reason: string, score: float}|null
     */
    private function scoreFromHistory(ProductPurchaseStat $stat, ?StapleRule $rule, Collection $recentTrips): ?array
    {
        $days = $stat->daysSinceLastPurchase();
        $interval = $stat->medianIntervalDays;
        $dueRatio = $interval !== null && $interval > 0 ? $days / $interval : 0.0;
        $tripsBought = $recentTrips->intersect($stat->transactionIds)->count();
        $frequency = min($stat->recentEvents / 6, 1.0);
        $isDue = $dueRatio >= 1.0;
        $isFrequent = $tripsBought >= self::MIN_FREQUENT_TRIPS;

        if (! $isDue && ! $isFrequent) {
            return null;
        }

        return [
            'kind' => $isDue ? 'replenish' : 'frequent',
            'reason' => $isDue
                ? __('You usually buy this every :interval days. Last bought :days days ago.', ['interval' => (int) round((float) $interval), 'days' => $days])
                : __('Purchased on :count of your last :total trips.', ['count' => $tripsBought, 'total' => $recentTrips->count()]),
            'score' => 0.35 * $frequency + 0.5 * (min($dueRatio, 2.0) / 2) + ($rule !== null ? 0.15 : 0.0),
        ];
    }

    /**
     * Products repeatedly bought alongside what this list already needs.
     *
     * @param  Collection<int, ProductPurchaseStat>  $stats
     * @return array<int, string>
     */
    private function coPurchased(Collection $stats, ?ShoppingList $list): array
    {
        if ($list === null) {
            return [];
        }

        $reasons = [];

        $needed = $list->items()->where('status', ListItemStatus::Needed)->whereNotNull('product_id')->get();

        foreach ($needed as $item) {
            /** @var ShoppingListItem $item */
            $anchor = $stats->get((int) $item->product_id);

            if ($anchor === null) {
                continue;
            }

            foreach ($stats as $productId => $stat) {
                if ($productId !== $item->product_id
                    && count(array_intersect($anchor->transactionIds, $stat->transactionIds)) >= self::MIN_SHARED_TRIPS) {
                    $reasons[$productId] ??= __('Often bought with :name.', ['name' => $item->name_snapshot]);
                }
            }
        }

        return $reasons;
    }

    /**
     * @param  Collection<int, int>  $hidden
     * @return array<int, array{kind: string, reason: string, score: float}>
     */
    private function recentFallback(Model $owner, Collection $hidden, bool $noHistory): array
    {
        if (! $noHistory) {
            return [];
        }

        return Product::query()->forOwner($owner)
            ->whereNotIn('id', $hidden)
            ->whereNotNull('last_used_at')
            ->orderByDesc('last_used_at')
            ->limit(self::RECENT_FALLBACK_LIMIT)
            ->pluck('id')
            ->mapWithKeys(fn (int $id, int $index): array => [$id => [
                'kind' => 'recent',
                'reason' => __('You added this recently.'),
                'score' => 0.1 - $index * 0.01,
            ]])
            ->all();
    }

    /**
     * Products already needed on an active list, so nothing is suggested twice.
     *
     * @return Collection<int, int>
     */
    public function neededProductIds(Model $owner): Collection
    {
        return ShoppingListItem::query()
            ->where('status', ListItemStatus::Needed)
            ->whereNotNull('product_id')
            ->whereIn('shopping_list_id', ShoppingList::query()
                ->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', $owner->getKey())
                ->where('status', ListStatus::Active)
                ->select('id'))
            ->pluck('product_id')
            ->map(fn ($id): int => (int) $id);
    }

    /**
     * @return Collection<int, int>
     */
    private function dismissedProductIds(User $user): Collection
    {
        return RecommendationDismissal::query()->where('user_id', $user->id)->inEffect()->pluck('product_id')
            ->map(fn ($id): int => (int) $id);
    }
}
