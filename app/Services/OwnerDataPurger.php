<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseTransaction;
use App\Models\ShoppingList;
use App\Models\ShoppingTrip;
use App\Models\StapleRule;
use App\Models\Store;
use Illuminate\Database\Eloquent\Model;

class OwnerDataPurger
{
    /**
     * Permanently remove everything held in a personal or household scope.
     */
    public function purge(Model $owner): void
    {
        $type = $owner->getMorphClass();
        $id = $owner->getKey();

        PurchaseTransaction::withTrashed()->where('owner_type', $type)->where('owner_id', $id)->forceDelete();

        $listIds = ShoppingList::withTrashed()->where('owner_type', $type)->where('owner_id', $id)->pluck('id');
        ShoppingTrip::query()->whereIn('shopping_list_id', $listIds)->delete();
        ShoppingList::withTrashed()->whereIn('id', $listIds)->forceDelete();

        StapleRule::query()->where('owner_type', $type)->where('owner_id', $id)->delete();
        Product::query()->forOwner($owner)->delete();
        Store::query()->forOwner($owner)->delete();
        Category::query()->forOwner($owner)->delete();
    }
}
