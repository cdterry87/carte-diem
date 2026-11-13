<?php

namespace App\Services;

use App\Enums\VerificationStatus;
use App\Models\PurchaseTransaction;
use App\Models\PurchaseTransactionItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Verified purchases with a total, grouped by month in the owner's time zone so every member
 * sees the same figures. Amounts in different currencies are never added together.
 */
class SpendingSummary
{
    public const PERIODS = [3, 6, 12, 24];

    /**
     * @return array{months: int, timezone: string, currencies: array<int, array<string, mixed>>, missing_total_count: int, needs_review_count: int}
     */
    public function for(Model $owner, int $months = 12): array
    {
        $timezone = OwnerTimezone::of($owner);
        $thisMonth = CarbonImmutable::now($timezone)->startOfMonth();
        $start = $thisMonth->subMonths($months - 1);
        // Comparisons reach a year behind the last complete month, whatever period is selected.
        $reach = $start->min($thisMonth->subMonths(13));

        /** @var Builder<PurchaseTransaction> $inPeriod */
        $inPeriod = PurchaseTransaction::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->where('purchased_at', '>=', $start->utc());

        $verified = (clone $inPeriod)->where('verification_status', VerificationStatus::Verified);

        $counted = PurchaseTransaction::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->where('purchased_at', '>=', $reach->utc())
            ->where('verification_status', VerificationStatus::Verified)
            ->whereNotNull('total_minor')
            ->get(['id', 'merchant_name_snapshot', 'purchased_at', 'currency_code', 'total_minor']);
        $rows = $counted->filter(fn (PurchaseTransaction $purchase): bool => $purchase->purchased_at->gte($start));
        $lines = $this->pricedLines($rows);

        return [
            'months' => $months,
            'timezone' => $timezone,
            'currencies' => $rows->groupBy('currency_code')
                ->map(fn (Collection $purchases, string $currency): array => [
                    ...$this->summarize($purchases, $currency, $start, $months, $timezone),
                    ...$this->summarizeLines($lines->whereIn('purchase_transaction_id', $purchases->pluck('id')), $purchases, $start, $months, $timezone),
                    'comparison' => $this->compare($counted->where('currency_code', $currency), $thisMonth, $timezone),
                ])
                ->values()
                ->all(),
            'missing_total_count' => (clone $verified)->whereNull('total_minor')->count(),
            'needs_review_count' => (clone $inPeriod)->where('verification_status', VerificationStatus::NeedsReview)->count(),
        ];
    }

    /**
     * @param  Collection<int, PurchaseTransaction>  $purchases
     * @return array<string, mixed>
     */
    private function summarize(Collection $purchases, string $currency, CarbonImmutable $start, int $months, string $timezone): array
    {
        $byMonth = $purchases->groupBy(fn (PurchaseTransaction $purchase): string => $this->monthOf($purchase, $timezone));

        return [
            'currency_code' => $currency,
            'total_minor' => (int) $purchases->sum('total_minor'),
            'purchase_count' => $purchases->count(),
            'by_month' => collect(range($months - 1, 0))->map(function (int $offset) use ($byMonth, $start): array {
                $month = $start->addMonths($offset)->format('Y-m');
                /** @var Collection<int, PurchaseTransaction> $inMonth */
                $inMonth = $byMonth->get($month, collect());

                return ['month' => $month, 'total_minor' => (int) $inMonth->sum('total_minor'), 'purchase_count' => $inMonth->count()];
            })->all(),
            'by_store' => $purchases
                ->groupBy(fn (PurchaseTransaction $purchase): string => mb_strtolower(trim((string) $purchase->merchant_name_snapshot)))
                ->map(fn (Collection $group): array => [
                    'name' => $group->first()?->merchant_name_snapshot,
                    'total_minor' => (int) $group->sum('total_minor'),
                    'purchase_count' => $group->count(),
                ])
                ->sortByDesc('total_minor')
                ->values()
                ->all(),
        ];
    }

    /**
     * Priced lines from the purchases being counted, so category figures describe the same
     * purchases as the totals beside them.
     *
     * @param  Collection<int, PurchaseTransaction>  $purchases
     * @return Collection<int, PurchaseTransactionItem>
     */
    private function pricedLines(Collection $purchases): Collection
    {
        return PurchaseTransactionItem::query()
            ->with('category:id,name')
            ->whereIn('purchase_transaction_id', $purchases->pluck('id'))
            ->whereNotNull('line_total_minor')
            ->get(['id', 'purchase_transaction_id', 'line_total_minor', 'category_id', 'category_name_snapshot']);
    }

    /**
     * @param  Collection<int, PurchaseTransactionItem>  $lines
     * @param  Collection<int, PurchaseTransaction>  $purchases
     * @return array{itemized_minor: int, by_category: array<int, array<string, mixed>>}
     */
    private function summarizeLines(Collection $lines, Collection $purchases, CarbonImmutable $start, int $months, string $timezone): array
    {
        $monthOf = $purchases->mapWithKeys(fn (PurchaseTransaction $purchase): array => [$purchase->id => $this->monthOf($purchase, $timezone)]);
        $nameOf = fn (PurchaseTransactionItem $line): ?string => $line->category->name ?? $line->category_name_snapshot;

        return [
            'itemized_minor' => (int) $lines->sum('line_total_minor'),
            'by_category' => $lines
                ->groupBy(fn (PurchaseTransactionItem $line): string => mb_strtolower((string) $nameOf($line)))
                ->map(fn (Collection $group): array => [
                    'name' => $nameOf($group->first()),
                    'total_minor' => (int) $group->sum('line_total_minor'),
                    'purchase_count' => $group->unique('purchase_transaction_id')->count(),
                    'by_month' => collect(range($months - 1, 0))->map(function (int $offset) use ($group, $monthOf, $start): array {
                        $month = $start->addMonths($offset)->format('Y-m');
                        $inMonth = $group->filter(fn (PurchaseTransactionItem $line): bool => $monthOf[$line->purchase_transaction_id] === $month);

                        return ['month' => $month, 'total_minor' => (int) $inMonth->sum('line_total_minor'), 'purchase_count' => $inMonth->unique('purchase_transaction_id')->count()];
                    })->all(),
                ])
                ->sortByDesc('total_minor')
                ->values()
                ->all(),
        ];
    }

    /**
     * The last complete month against the one before and the same month a year earlier. The
     * current month is left out because a partial month always looks like a drop. An earlier
     * month with no purchases is null, since that may only mean nothing was recorded.
     *
     * @param  Collection<int, PurchaseTransaction>  $purchases
     * @return array{month: string, total_minor: int, previous_month_minor: int|null, last_year_minor: int|null}|null
     */
    private function compare(Collection $purchases, CarbonImmutable $thisMonth, string $timezone): ?array
    {
        $byMonth = $purchases->groupBy(fn (PurchaseTransaction $purchase): string => $this->monthOf($purchase, $timezone));
        $totalFor = fn (CarbonImmutable $month): ?int => $byMonth->has($month->format('Y-m')) ? (int) $byMonth->get($month->format('Y-m'))->sum('total_minor') : null;
        $month = $thisMonth->subMonth();
        $total = $totalFor($month);

        return $total === null ? null : [
            'month' => $month->format('Y-m'),
            'total_minor' => $total,
            'previous_month_minor' => $totalFor($month->subMonth()),
            'last_year_minor' => $totalFor($month->subYear()),
        ];
    }

    private function monthOf(PurchaseTransaction $purchase, string $timezone): string
    {
        return $purchase->purchased_at->setTimezone($timezone)->format('Y-m');
    }
}
