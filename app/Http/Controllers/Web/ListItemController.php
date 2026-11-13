<?php

namespace App\Http\Controllers\Web;

use App\Actions\Lists\AddListItem;
use App\Actions\Lists\UpdateListItem;
use App\Events\ListReordered;
use App\Http\Requests\Lists\ListItemRequest;
use App\Models\Product;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Services\ListItemSuggestions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ListItemController extends WebController
{
    public function store(ListItemRequest $request, ShoppingList $list, AddListItem $addItem): RedirectResponse
    {
        Gate::authorize('editItems', $list);

        /** @var Model $owner */
        $owner = $list->owner;
        /** @var array{name: string, quantity?: string|float|int|null, unit?: string|null, notes?: string|null} $input */
        $input = $request->safe()->only(['name', 'quantity', 'unit', 'notes']);

        $addItem->handle($list, $this->actor($request), $input, $request->categoryFor($owner));

        return back();
    }

    public function update(ListItemRequest $request, ShoppingList $list, string $item, UpdateListItem $updateItem): RedirectResponse
    {
        Gate::authorize('editItems', $list);

        /** @var Model $owner */
        $owner = $list->owner;
        $revision = $request->has('revision') ? $request->integer('revision') : null;

        $updateItem->handle($this->item($list, $item), $request->itemAttributes($owner), $revision);

        return back();
    }

    public function destroy(ShoppingList $list, string $item): RedirectResponse
    {
        Gate::authorize('editItems', $list);

        $this->item($list, $item)->delete();

        return back();
    }

    public function restore(ShoppingList $list, string $item): RedirectResponse
    {
        Gate::authorize('editItems', $list);

        $list->items()->onlyTrashed()->where('uuid', $item)->firstOrFail()->restore();

        return back();
    }

    public function reorder(Request $request, ShoppingList $list): RedirectResponse
    {
        Gate::authorize('editItems', $list);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:1000'],
            'items.*' => ['uuid', 'distinct'],
        ]);

        DB::transaction(function () use ($list, $validated): void {
            foreach ($validated['items'] as $index => $uuid) {
                $list->items()->where('uuid', $uuid)->update(['sort_order' => $index + 1]);
            }

            ListReordered::dispatch($list->uuid);
        });

        return back();
    }

    public function favoriteProduct(Request $request, Product $product): RedirectResponse
    {
        Gate::authorize('view', $product);

        $product->favoritedBy()->toggle([$this->actor($request)->id]);

        return back();
    }

    public function suggestions(Request $request, ShoppingList $list, ListItemSuggestions $suggestions): JsonResponse
    {
        Gate::authorize('editItems', $list);

        return response()->json(['data' => $suggestions->forList($list, $this->actor($request), $request->string('q')->toString())]);
    }

    private function item(ShoppingList $list, string $uuid): ShoppingListItem
    {
        /** @var ShoppingListItem */
        return $list->items()->where('uuid', $uuid)->firstOrFail();
    }
}
