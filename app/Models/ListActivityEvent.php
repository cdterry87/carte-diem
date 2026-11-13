<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $shopping_list_id
 * @property int|null $actor_id
 * @property string $action
 * @property string $item_name
 * @property-read User|null $actor
 */
#[Fillable(['shopping_list_id', 'actor_id', 'action', 'item_name', 'processed_at'])]
class ListActivityEvent extends Model
{
    public const UPDATED_AT = null;

    public const ADDED = 'added';

    public const REMOVED = 'removed';

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
