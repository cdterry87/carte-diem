<?php

namespace App\Services;

use App\Enums\VerificationStatus;
use App\Models\PurchaseTransaction;
use App\Models\PurchaseTransactionItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Unit prices from confirmed purchases, read at request time so corrections apply immediately.
 * Prices are only compared within one currency and one unit family. A line with no quantity
 * counts as one, and a line with no unit counts as each.
 */
class PriceHistory
{
    public const MONTHS = 24;

    /**
     * Only safe conversions. Metric and US measures stay apart. "oz" only matches itself, since
     * it can mean weight or volume, and so does a package unit such as "bag".
     *
     * @var array<string, array{0: string, 1: float}>
     */
    private const CONVERSIONS = [
        'g' => ['kg', 0.001],
        'ml' => ['l', 0.001],
        'qt' => ['gal', 0.25],
        'pt' => ['gal', 0.125],
        'dozen' => ['each', 12.0],
        'ea' => ['each', 1.0],
    ];

    /**
     * Products bought at a known price at least twice, most often bought first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function products(Model $owner): array
    {
        return $this->lines($owner)
            ->groupBy(fn (array $line): string => "{$line['product_uuid']}|{$line['currency_code']}|{$line['unit']}")
            ->filter(fn (Collection $group): bool => $group->count() >= 2)
            ->map(function (Collection $group): array {
                $first = $group->first();
                $last = $group->last();

                return [
                    'uuid' => $first['product_uuid'],
                    'name' => $first['product_name'],
                    'currency_code' => $first['currency_code'],
                    'unit' => $first['unit'],
                    'purchase_count' => $group->count(),
                    'latest_minor' => $last['unit_price_minor'],
                    'first_minor' => $first['unit_price_minor'],
                    'latest_at' => $last['purchased_at'],
                ];
            })
            ->sortByDesc('purchase_count')
            ->values()
            ->all();
    }

    /**
     * Every price for one product in this space, or null if it has none here.
     *
     * @return array{uuid: string, name: string, series: array<int, array<string, mixed>>}|null
     */
    public function product(Model $owner, string $uuid): ?array
    {
        $lines = $this->lines($owner, $uuid);
        $first = $lines->first();

        if ($first === null) {
            return null;
        }

        return [
            'uuid' => $first['product_uuid'],
            'name' => $first['product_name'],
            'series' => $lines
                ->groupBy(fn (array $line): string => "{$line['currency_code']}|{$line['unit']}")
                ->map(fn (Collection $group): array => [
                    'currency_code' => $group->first()['currency_code'],
                    'unit' => $group->first()['unit'],
                    'prices' => $group->map(fn (array $line): array => [
                        'purchased_at' => $line['purchased_at'],
                        'store' => $line['store'],
                        'unit_price_minor' => $line['unit_price_minor'],
                    ])->values()->all(),
                    'stores' => $group
                        ->groupBy(fn (array $line): string => mb_strtolower(trim((string) $line['store'])))
                        ->map(fn (Collection $visits): array => [
                            'name' => $visits->first()['store'],
                            'purchase_count' => $visits->count(),
                            'average_minor' => (int) round($visits->avg('unit_price_minor')),
                            'latest_minor' => $visits->last()['unit_price_minor'],
                            'latest_at' => $visits->last()['purchased_at'],
                        ])
                        ->sortBy('average_minor')
                        ->values()
                        ->all(),
                ])
                ->sortByDesc(fn (array $series): int => count($series['prices']))
                ->values()
                ->all(),
        ];
    }

    /**
     * Oldest first.
     *
     * @return Collection<int, array{product_uuid: string, product_name: string, currency_code: string, unit: string, unit_price_minor: int, store: string|null, purchased_at: string}>
     */
    private function lines(Model $owner, ?string $productUuid = null): Collection
    {
        $purchases = PurchaseTransaction::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->where('verification_status', VerificationStatus::Verified)
            ->where('purchased_at', '>=', Carbon::now()->subMonths(self::MONTHS))
            ->orderBy('purchased_at')
            ->orderBy('id')
            ->get(['id', 'merchant_name_snapshot', 'purchased_at', 'currency_code'])
            ->keyBy('id');

        $items = PurchaseTransactionItem::query()
            ->with('product:id,uuid,canonical_name')
            ->whereIn('purchase_transaction_id', $purchases->keys())
            ->whereNotNull('product_id')
            ->where('line_total_minor', '>', 0)
            ->when($productUuid !== null, fn ($query) => $query->whereRelation('product', 'uuid', $productUuid))
            ->get(['id', 'purchase_transaction_id', 'product_id', 'quantity', 'unit_code', 'line_total_minor']);

        $positions = $purchases->keys()->flip();

        return $items->toBase()
            ->sortBy(fn (PurchaseTransactionItem $item): int => $positions[$item->purchase_transaction_id])
            ->values()
            ->map(function (PurchaseTransactionItem $item) use ($purchases): array {
                /** @var PurchaseTransaction $purchase */
                $purchase = $purchases[$item->purchase_transaction_id];
                [$unit, $factor] = $this->normalizeUnit($item->unit_code);

                return [
                    'product_uuid' => $item->product->uuid,
                    'product_name' => $item->product->canonical_name,
                    'currency_code' => $purchase->currency_code,
                    'unit' => $unit,
                    'unit_price_minor' => (int) round((int) $item->line_total_minor / ((float) ($item->quantity ?? 1) * $factor)),
                    'store' => $purchase->merchant_name_snapshot,
                    'purchased_at' => $purchase->purchased_at->toIso8601String(),
                ];
            });
    }

    /**
     * @return array{0: string, 1: float}
     */
    private function normalizeUnit(?string $unit): array
    {
        $unit = mb_strtolower(trim((string) $unit));

        return self::CONVERSIONS[$unit] ?? [$unit === '' ? 'each' : $unit, 1.0];
    }
}
