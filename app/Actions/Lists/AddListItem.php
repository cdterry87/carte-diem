<?php

namespace App\Actions\Lists;

use App\Enums\ListItemStatus;
use App\Models\Category;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\User;
use App\Services\ItemEntryResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AddListItem
{
    public function __construct(private readonly ItemEntryResolver $entries) {}

    /**
     * @param  array{name: string, quantity?: string|float|int|null, unit?: string|null, notes?: string|null}  $input
     */
    public function handle(ShoppingList $list, User $user, array $input, ?Category $category = null): ShoppingListItem
    {
        /** @var Model $owner */
        $owner = $list->owner;

        return DB::transaction(function () use ($list, $user, $input, $category, $owner): ShoppingListItem {
            $entry = $this->entries->resolve($owner, $input, $category);
            $product = $entry->product;

            /** @var ShoppingListItem|null $checkedOff */
            $checkedOff = $list->items()
                ->where('product_id', $product->id)
                ->where('status', ListItemStatus::Satisfied)
                ->first();

            // Adding something already checked off means it is needed again, not a second copy.
            if ($checkedOff !== null) {
                $checkedOff->status = ListItemStatus::Needed;
                $checkedOff->needs_review = false;
                $checkedOff->requested_quantity = $entry->quantity ?? $checkedOff->requested_quantity;
                $checkedOff->unit_code = $entry->unit ?? $checkedOff->unit_code;
                $checkedOff->category_id = $category->id ?? $checkedOff->category_id;
                $checkedOff->notes = $input['notes'] ?? $checkedOff->notes;
                $checkedOff->revision++;
                $checkedOff->save();

                return $checkedOff->load(['category', 'product', 'addedBy']);
            }

            return $list->items()->create([
                'product_id' => $product->id,
                'name_snapshot' => $entry->name,
                'requested_quantity' => $entry->quantity,
                'unit_code' => $entry->unit,
                'category_id' => $category->id ?? $product->default_category_id,
                'notes' => $input['notes'] ?? null,
                'sort_order' => (int) $list->items()->withTrashed()->max('sort_order') + 1,
                'added_by' => $user->id,
            ])->load(['category', 'product', 'addedBy']);
        });
    }
}
