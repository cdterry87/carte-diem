<?php

namespace App\Models;

use App\Enums\ListShareRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $shopping_list_id
 * @property int $user_id
 * @property ListShareRole $role
 * @property-read User $user
 * @property-read ShoppingList $shoppingList
 */
#[Fillable(['shopping_list_id', 'user_id', 'role', 'invited_by'])]
class ListShare extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['role' => ListShareRole::class];
    }

    /**
     * @return BelongsTo<ShoppingList, $this>
     */
    public function shoppingList(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
