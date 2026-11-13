<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

class ProductResolver
{
    public function __construct(private readonly CategoryInferrer $inferrer) {}

    public static function normalize(string $name): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $name)));
    }

    /**
     * Find the owner's product for a free-text name, creating it when unknown.
     */
    public function resolve(Model $owner, string $name, ?Category $category = null): Product
    {
        $normalized = self::normalize($name);

        $product = Product::query()->forOwner($owner)
            ->where(function ($query) use ($normalized) {
                $query->where('normalized_name', $normalized)
                    ->orWhereHas('aliases', fn ($aliases) => $aliases->where('normalized_alias', $normalized));
            })
            ->with('defaultCategory')
            ->first();

        if ($product === null) {
            $product = Product::query()->createOrFirst([
                'scope_type' => $owner->getMorphClass(),
                'scope_id' => $owner->getKey(),
                'normalized_name' => $normalized,
            ], [
                'canonical_name' => trim($name),
                'default_category_id' => ($category ?? $this->inferredCategory($name))?->id,
            ]);
            $product->aliases()->firstOrCreate(['normalized_alias' => $normalized]);
        } elseif ($category !== null && $product->default_category_id !== $category->id) {
            $product->update(['default_category_id' => $category->id]);
        }

        $product->update(['last_used_at' => now()]);

        return $product->load('defaultCategory');
    }

    private function inferredCategory(string $name): ?Category
    {
        return Category::query()
            ->where('scope_type', Category::SYSTEM)
            ->where('slug', $this->inferrer->infer($name))
            ->first();
    }
}
