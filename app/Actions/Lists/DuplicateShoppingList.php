<?php

namespace App\Actions\Lists;

use App\Enums\ListItemStatus;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DuplicateShoppingList
{
    public function handle(ShoppingList $list, User $user, ?string $title = null): ShoppingList
    {
        return DB::transaction(function () use ($list, $user, $title): ShoppingList {
            $copy = ShoppingList::query()->create([
                'owner_type' => $list->owner_type,
                'owner_id' => $list->owner_id,
                'title' => $title ?? __(':title (copy)', ['title' => $list->title]),
                'description' => $list->description,
                'default_store_id' => $list->default_store_id,
                'source_list_id' => $list->id,
                'created_by' => $user->id,
            ]);

            $list->items()->orderBy('sort_order')->get()->each(function (ShoppingListItem $item) use ($copy, $user): void {
                $copy->items()->create([
                    'product_id' => $item->product_id,
                    'name_snapshot' => $item->name_snapshot,
                    'requested_quantity' => $item->requested_quantity,
                    'unit_code' => $item->unit_code,
                    'category_id' => $item->category_id,
                    'notes' => $item->notes,
                    'sort_order' => $item->sort_order,
                    'status' => ListItemStatus::Needed,
                    'added_by' => $user->id,
                ]);
            });

            return $copy;
        });
    }
}
