<?php

namespace App\Http\Controllers\Web;

use App\Actions\Lists\CreateShoppingList;
use App\Actions\Lists\DuplicateShoppingList;
use App\Enums\ListStatus;
use App\Http\Requests\Lists\ShoppingListRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ListItemResource;
use App\Http\Resources\ListShareInvitationResource;
use App\Http\Resources\ListShareResource;
use App\Http\Resources\RecommendationResource;
use App\Http\Resources\ShoppingListResource;
use App\Http\Resources\StoreResource;
use App\Models\Category;
use App\Models\ShoppingList;
use App\Models\Store;
use App\Services\ListQuery;
use App\Services\Recommendations;
use App\Services\StoreCategoryOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ListController extends WebController
{
    public function index(Request $request, ListQuery $lists): Response
    {
        $filters = $request->validate([
            'scope' => ['sometimes', 'nullable', 'string', 'max:36'],
            'status' => ['sometimes', 'nullable', 'in:active,archived,deleted'],
            'q' => ['sometimes', 'nullable', 'string', 'max:160'],
        ]);

        $user = $this->actor($request);

        return Inertia::render('lists/index', [
            'lists' => $this->props(ShoppingListResource::collection(
                $lists->forUser($user, $filters['scope'] ?? null, $filters['status'] ?? null, $filters['q'] ?? null)->limit(100)->get()
            )),
            'filters' => [
                'scope' => $filters['scope'] ?? null,
                'status' => $filters['status'] ?? 'active',
                'q' => $filters['q'] ?? '',
            ],
        ]);
    }

    public function store(ShoppingListRequest $request, CreateShoppingList $create): RedirectResponse
    {
        $owner = $request->owner();
        /** @var array{title: string, description?: string|null, default_store_id?: int|null} $attributes */
        $attributes = $request->listAttributes($owner);
        $list = $create->handle($this->actor($request), $owner, $attributes);

        return to_route('lists.show', $list);
    }

    public function show(Request $request, ShoppingList $list, Recommendations $recommendations, StoreCategoryOrder $order): Response
    {
        Gate::authorize('view', $list);

        $list->load(['owner', 'defaultStore'])->loadCount(['items', 'neededItems']);
        $owner = $list->owner;
        $inScope = $owner !== null && $this->actor($request)->belongsToScope($owner);
        $canShare = Gate::allows('share', $list);

        return Inertia::render('lists/show', [
            'list' => $this->props(ShoppingListResource::make($list)),
            'items' => $this->props(ListItemResource::collection(
                $list->items()->with(['category', 'product', 'addedBy'])->orderBy('sort_order')->get()
            )),
            'categories' => $owner === null ? [] : $this->props(CategoryResource::collection(Category::query()->availableTo($owner)->get())),
            'stores' => $owner === null || ! $inScope ? [] : $this->props(StoreResource::collection(Store::query()->forOwner($owner)->orderBy('name')->get())),
            'suggestions' => $inScope && Gate::allows('editItems', $list)
                ? $this->props(RecommendationResource::collection($recommendations->forOwner($owner, $this->actor($request), $list, 6)))
                : [],
            'categoryOrder' => Category::query()
                ->whereIn('id', $order->positions($list->defaultStore)->keys())
                ->get()
                ->mapWithKeys(fn (Category $category): array => [$category->uuid => $order->positions($list->defaultStore)->get($category->id)]),
            'favoriteProducts' => $inScope ? $this->actor($request)->favoriteProducts()->pluck('products.uuid') : [],
            'shares' => $canShare ? $this->props(ListShareResource::collection($list->shares()->with('user')->get())) : [],
            'shareInvitations' => $canShare ? $this->props(ListShareInvitationResource::collection($list->shareInvitations()->pending()->latest()->get())) : [],
        ]);
    }

    public function update(ShoppingListRequest $request, ShoppingList $list): RedirectResponse
    {
        Gate::authorize('update', $list);

        $list->update($request->listAttributes($request->targetOwner()));

        return $this->backWith(__('List updated.'));
    }

    public function destroy(ShoppingList $list): RedirectResponse
    {
        Gate::authorize('delete', $list);

        $list->delete();

        return to_route('lists.index');
    }

    public function restore(string $list): RedirectResponse
    {
        $model = ShoppingList::onlyTrashed()->where('uuid', $list)->firstOrFail();
        Gate::authorize('restore', $model);

        $model->restore();

        return to_route('lists.show', $model);
    }

    public function archive(ShoppingList $list): RedirectResponse
    {
        Gate::authorize('update', $list);

        $list->update(['status' => ListStatus::Archived]);

        return $this->backWith(__('List archived.'));
    }

    public function unarchive(ShoppingList $list): RedirectResponse
    {
        Gate::authorize('update', $list);

        $list->update(['status' => ListStatus::Active]);

        return $this->backWith(__('List restored from archive.'));
    }

    public function duplicate(Request $request, ShoppingList $list, DuplicateShoppingList $duplicate): RedirectResponse
    {
        Gate::authorize('duplicate', $list);

        return to_route('lists.show', $duplicate->handle($list, $this->actor($request)));
    }

    public function favorite(Request $request, ShoppingList $list): RedirectResponse
    {
        Gate::authorize('view', $list);

        $list->favoritedBy()->toggle([$this->actor($request)->id]);

        return back();
    }
}
