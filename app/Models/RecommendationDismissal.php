<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A null dismissed_until means muted until the user lifts it.
 *
 * @property int $id
 * @property int $user_id
 * @property int $product_id
 * @property string $reason
 * @property Carbon|null $dismissed_until
 * @property-read Product $product
 */
#[Fillable(['user_id', 'product_id', 'reason', 'dismissed_until'])]
class RecommendationDismissal extends Model
{
    public const MUTE = 'mute';

    public const SNOOZE = 'snooze';

    public const DISMISS = 'dismiss';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['dismissed_until' => 'datetime'];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeInEffect(Builder $query): Builder
    {
        return $query->where(fn (Builder $active) => $active->whereNull('dismissed_until')->orWhere('dismissed_until', '>', now()));
    }
}
