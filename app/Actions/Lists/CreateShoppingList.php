<?php

namespace App\Actions\Lists;

use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CreateShoppingList
{
    /**
     * @param  array{title: string, description?: string|null, default_store_id?: int|null}  $attributes
     */
    public function handle(User $user, Model $owner, array $attributes): ShoppingList
    {
        return ShoppingList::query()->create([
            ...$attributes,
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
            'created_by' => $user->id,
        ]);
    }
}
