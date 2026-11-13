<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ListItemSuggestions
{
    public function __construct(private readonly Recommendations $recommendations) {}

    /**
     * Autocomplete names for a list. Scope members draw on the owner's products;
     * guests only see names on the list plus their own personal products.
     *
     * @return Collection<int, array{name: string, category: string|null, is_favorite: bool, reason: string|null}>
     */
    public function forList(ShoppingList $list, User $user, string $term, int $limit = 10): Collection
    {
        $owner = $list->owner;
        $term = ProductResolver::normalize($term);

        if ($owner !== null && $user->belongsToScope($owner)) {
            return $this->forScopeMember($owner, $user, $list, $term, $limit);
        }

        $listNames = $list->items()
            ->with('category')
            ->when($term !== '', fn ($query) => $query->where('name_snapshot', 'like', $this->like($term)))
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (ShoppingListItem $item): array => $this->suggestion($item->name_snapshot, $item->category?->name, false, null));

        return $listNames
            ->concat($this->fromProducts($user, $user, $term, $limit))
            ->unique(fn (array $suggestion): string => ProductResolver::normalize($suggestion['name']))
            ->take($limit)
            ->values();
    }

    /**
     * Autocomplete from the owner's products, for items not tied to a list.
     *
     * @return Collection<int, array{name: string, category: string|null, is_favorite: bool, reason: string|null}>
     */
    public function forOwner(Model $owner, User $user, string $term, int $limit = 10): Collection
    {
        return $this->fromProducts($owner, $user, ProductResolver::normalize($term), $limit);
    }

    /**
     * @return Collection<int, array{name: string, category: string|null, is_favorite: bool, reason: string|null}>
     */
    private function forScopeMember(Model $owner, User $user, ShoppingList $list, string $term, int $limit): Collection
    {
        $favoriteIds = $user->favoriteProducts()->pluck('products.id');

        $recommended = $this->recommendations->forOwner($owner, $user, $list, $limit)
            ->filter(fn (Recommendation $recommendation): bool => $term === '' || str_contains($recommendation->product->normalized_name, $term))
            ->map(fn (Recommendation $recommendation): array => $this->suggestion($recommendation->product->canonical_name, $recommendation->product->defaultCategory?->name, $favoriteIds->contains($recommendation->product->id), $recommendation->reason));

        return $recommended
            ->concat($this->fromProducts($owner, $user, $term, $limit))
            ->unique(fn (array $suggestion): string => ProductResolver::normalize($suggestion['name']))
            ->take($limit)
            ->values();
    }

    /**
     * @return Collection<int, array{name: string, category: string|null, is_favorite: bool, reason: string|null}>
     */
    private function fromProducts(Model $owner, User $user, string $term, int $limit): Collection
    {
        $favoriteIds = $user->favoriteProducts()->pluck('products.id');

        return Product::query()->forOwner($owner)
            ->with('defaultCategory')
            ->when($term !== '', fn ($query) => $query->where('normalized_name', 'like', $this->like($term)))
            ->orderByRaw('exists (select 1 from product_favorites where product_favorites.product_id = products.id and product_favorites.user_id = ?) desc', [$user->id])
            ->orderByDesc('last_used_at')
            ->limit($limit)
            ->get()
            ->map(fn (Product $product): array => $this->suggestion($product->canonical_name, $product->defaultCategory?->name, $favoriteIds->contains($product->id), null))
            ->values();
    }

    /**
     * @return array{name: string, category: string|null, is_favorite: bool, reason: string|null}
     */
    private function suggestion(string $name, ?string $category, bool $isFavorite, ?string $reason): array
    {
        return ['name' => $name, 'category' => $category, 'is_favorite' => $isFavorite, 'reason' => $reason];
    }

    private function like(string $term): string
    {
        return '%'.addcslashes($term, '%_\\').'%';
    }
}
