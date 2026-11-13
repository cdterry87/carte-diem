<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StoreCategoryOrder
{
    /**
     * Categories the store has no position for follow in their usual order.
     */
    private const UNPOSITIONED_OFFSET = 100000;

    /**
     * @return Collection<int, int> Store position, keyed by category id.
     */
    public function positions(?Store $store): Collection
    {
        if ($store === null) {
            return collect();
        }

        return DB::table('store_category_orders')->where('store_id', $store->id)->pluck('sort_order', 'category_id')
            ->map(fn ($position): int => (int) $position);
    }

    /**
     * @param  Collection<int, int>  $positions
     */
    public function sortValue(Collection $positions, ?int $categoryId, int $defaultSort): int
    {
        return $categoryId !== null && $positions->has($categoryId)
            ? (int) $positions->get($categoryId)
            : self::UNPOSITIONED_OFFSET + $defaultSort;
    }

    /**
     * @param  array<int, string>  $categoryUuids
     */
    public function save(Store $store, array $categoryUuids): void
    {
        /** @var Model $owner */
        $owner = $store->scope;
        $categoryIds = Category::query()->availableTo($owner)->whereIn('uuid', $categoryUuids)->pluck('id', 'uuid');

        if ($categoryIds->count() !== count($categoryUuids)) {
            throw ValidationException::withMessages(['categories' => __('One of those categories is not available for this store.')]);
        }

        DB::transaction(function () use ($store, $categoryUuids, $categoryIds): void {
            DB::table('store_category_orders')->where('store_id', $store->id)->delete();

            foreach ($categoryUuids as $index => $uuid) {
                DB::table('store_category_orders')->insert([
                    'store_id' => $store->id,
                    'category_id' => $categoryIds->get($uuid),
                    'sort_order' => $index + 1,
                ]);
            }
        });
    }
}
