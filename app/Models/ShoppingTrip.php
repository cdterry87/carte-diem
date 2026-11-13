<?php

namespace App\Models;

use App\Enums\TripStatus;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $shopping_list_id
 * @property int|null $store_id
 * @property TripStatus $status
 * @property int|null $started_by
 * @property int|null $completed_by
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property int $revision
 * @property int|null $total_minor
 * @property-read ShoppingList $shoppingList
 * @property-read Store|null $store
 * @property-read User|null $starter
 * @property-read User|null $completer
 * @property-read PurchaseTransaction|null $purchase
 */
#[Fillable(['shopping_list_id', 'store_id', 'status', 'started_by', 'completed_by', 'started_at', 'completed_at', 'cancelled_at', 'revision', 'total_minor'])]
class ShoppingTrip extends Model
{
    use HasPublicUuid;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'active', 'revision' => 1];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TripStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * History outlives a soft-deleted list.
     *
     * @return BelongsTo<ShoppingList, $this>
     */
    public function shoppingList(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class)->withTrashed();
    }

    /**
     * @return HasMany<ShoppingTripItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ShoppingTripItem::class);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * @return HasOne<PurchaseTransaction, $this>
     */
    public function purchase(): HasOne
    {
        return $this->hasOne(PurchaseTransaction::class);
    }

    public function isActive(): bool
    {
        return $this->status === TripStatus::Active;
    }
}
