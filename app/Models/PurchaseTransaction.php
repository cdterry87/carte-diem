<?php

namespace App\Models;

use App\Enums\EntryMethod;
use App\Enums\VerificationStatus;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property string $owner_type
 * @property int $owner_id
 * @property int|null $shopping_trip_id
 * @property int|null $store_id
 * @property string|null $merchant_name_snapshot
 * @property Carbon $purchased_at
 * @property string $currency_code
 * @property int|null $total_minor
 * @property EntryMethod $entry_method
 * @property VerificationStatus $verification_status
 * @property int $revision
 * @property int|null $subtotal_minor
 * @property int|null $tax_minor
 * @property int|null $discount_minor
 * @property int|null $created_by
 * @property Carbon|null $deleted_at
 * @property-read Model|null $owner
 * @property-read ShoppingTrip|null $trip
 */
#[Fillable(['owner_type', 'owner_id', 'shopping_trip_id', 'store_id', 'merchant_name_snapshot', 'purchased_at', 'currency_code', 'subtotal_minor', 'tax_minor', 'discount_minor', 'total_minor', 'entry_method', 'verification_status', 'revision', 'source_kind', 'source_identifier', 'idempotency_key', 'created_by'])]
class PurchaseTransaction extends Model
{
    use HasPublicUuid, SoftDeletes;

    /**
     * Set here too, so a purchase just created reports it without a reload.
     *
     * @var array<string, int>
     */
    protected $attributes = ['revision' => 1];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purchased_at' => 'datetime',
            'entry_method' => EntryMethod::class,
            'verification_status' => VerificationStatus::class,
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<PurchaseTransactionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseTransactionItem::class);
    }

    /**
     * @return BelongsTo<ShoppingTrip, $this>
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(ShoppingTrip::class, 'shopping_trip_id');
    }

    public static function currencyFor(Model $owner): string
    {
        return $owner instanceof Household ? $owner->currency_code : 'USD';
    }

    /**
     * Personal and household purchases only; list guests have no access.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $query) use ($user) {
            $query->where(fn (Builder $personal) => $personal->where('owner_type', 'user')->where('owner_id', $user->id))
                ->orWhere(fn (Builder $household) => $household->where('owner_type', 'household')->whereIn('owner_id', $user->households()->select('households.id')));
        });
    }
}
