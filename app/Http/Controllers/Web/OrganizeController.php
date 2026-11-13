<?php

namespace App\Http\Controllers\Web;

use App\Actions\Lists\SaveCategory;
use App\Http\Requests\Lists\CategoryRequest;
use App\Http\Requests\Lists\StoreRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\OwnerResource;
use App\Http\Resources\StoreResource;
use App\Models\Category;
use App\Models\Store;
use App\Services\OwnerResolver;
use App\Services\StoreCategoryOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrganizeController extends WebController
{
    public function index(Request $request, OwnerResolver $owners): Response
    {
        $household = $request->query('household');
        $owner = $owners->resolve(
            $this->actor($request),
            is_string($household) ? 'household' : 'user',
            is_string($household) ? $household : null,
        );

        return Inertia::render('organize/index', [
            'owner' => $this->props(OwnerResource::make($owner)),
            'stores' => $this->props(StoreResource::collection(Store::query()->forOwner($owner)->orderBy('name')->get())),
            'storeOrders' => Store::query()->forOwner($owner)->get()->mapWithKeys(fn (Store $store): array => [
                $store->uuid => Category::query()
                    ->join('store_category_orders', 'store_category_orders.category_id', '=', 'categories.id')
                    ->where('store_category_orders.store_id', $store->id)
                    ->orderBy('store_category_orders.sort_order')
                    ->pluck('categories.uuid'),
            ]),
            'categories' => $this->props(CategoryResource::collection(Category::query()->availableTo($owner)->get())),
        ]);
    }

    public function storeStore(StoreRequest $request): RedirectResponse
    {
        $owner = $request->owner();

        Store::query()->create([
            ...$request->safe()->except('owner'),
            'scope_type' => $owner->getMorphClass(),
            'scope_id' => $owner->getKey(),
        ]);

        return $this->backWith(__('Store added.'));
    }

    public function updateStore(StoreRequest $request, Store $store): RedirectResponse
    {
        Gate::authorize('update', $store);

        $store->update($request->validated());

        return $this->backWith(__('Store updated.'));
    }

    public function destroyStore(Store $store): RedirectResponse
    {
        Gate::authorize('delete', $store);

        $store->delete();

        return $this->backWith(__('Store removed.'));
    }

    public function updateCategoryOrder(Request $request, Store $store, StoreCategoryOrder $order): RedirectResponse
    {
        Gate::authorize('update', $store);

        $validated = $request->validate([
            'categories' => ['present', 'array', 'max:200'],
            'categories.*' => ['uuid', 'distinct'],
        ]);

        $order->save($store, array_values($validated['categories']));

        return $this->backWith(__('Aisle order saved.'));
    }

    public function storeCategory(CategoryRequest $request, SaveCategory $save): RedirectResponse
    {
        /** @var array{name: string, sort_order?: int} $validated */
        $validated = $request->safe()->except('owner');

        $save->create($request->owner(), $validated);

        return $this->backWith(__('Category added.'));
    }

    public function updateCategory(CategoryRequest $request, Category $category, SaveCategory $save): RedirectResponse
    {
        Gate::authorize('update', $category);

        /** @var array{name?: string, sort_order?: int} $validated */
        $validated = $request->validated();
        $save->update($category, $validated);

        return $this->backWith(__('Category updated.'));
    }

    public function destroyCategory(Category $category): RedirectResponse
    {
        Gate::authorize('delete', $category);

        $category->update(['is_active' => false]);

        return $this->backWith(__('Category removed.'));
    }
}
