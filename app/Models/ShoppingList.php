<?php

namespace App\Models;

use App\Enums\HouseholdRole;
use App\Enums\ListItemStatus;
use App\Enums\ListShareRole;
use App\Enums\ListStatus;
use App\Enums\TripStatus;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $uuid
 * @property string $owner_type
 * @property int $owner_id
 * @property string $title
 * @property string|null $description
 * @property int|null $default_store_id
 * @property ListStatus $status
 * @property int|null $source_list_id
 * @property int|null $created_by
 * @property-read Model|null $owner
 * @property-read Store|null $defaultStore
 */
#[Fillable(['owner_type', 'owner_id', 'title', 'description', 'default_store_id', 'status', 'source_list_id', 'created_by'])]
class ShoppingList extends Model
{
    use HasPublicUuid, SoftDeletes;

    /**
     * @var array<string, string>
     */
    protected $attributes = ['status' => 'active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => ListStatus::class];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<ShoppingListItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ShoppingListItem::class);
    }

    /**
     * @return HasMany<ShoppingListItem, $this>
     */
    public function neededItems(): HasMany
    {
        return $this->items()->where('status', ListItemStatus::Needed);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function defaultStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'default_store_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'shopping_list_favorites');
    }

    /**
     * @return HasMany<ShoppingTrip, $this>
     */
    public function trips(): HasMany
    {
        return $this->hasMany(ShoppingTrip::class);
    }

    /**
     * @return HasOne<ShoppingTrip, $this>
     */
    public function activeTrip(): HasOne
    {
        return $this->hasOne(ShoppingTrip::class)->where('status', TripStatus::Active);
    }

    /**
     * @return HasMany<ListShare, $this>
     */
    public function shares(): HasMany
    {
        return $this->hasMany(ListShare::class);
    }

    /**
     * @return HasMany<ListShareInvitation, $this>
     */
    public function shareInvitations(): HasMany
    {
        return $this->hasMany(ListShareInvitation::class);
    }

    /**
     * Lists in the user's personal scope, their households, or shared with them.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        $householdIds = $user->households()->select('households.id');

        return $query->where(function (Builder $query) use ($user, $householdIds) {
            $query->where(fn (Builder $personal) => $personal->where('owner_type', 'user')->where('owner_id', $user->id))
                ->orWhere(fn (Builder $household) => $household->where('owner_type', 'household')->whereIn('owner_id', $householdIds))
                ->orWhere(function (Builder $shared) use ($user) {
                    $shared->whereHas('shares', fn (Builder $shares) => $shares->where('user_id', $user->id))
                        ->where(fn (Builder $live) => $live->where('owner_type', 'user')
                            ->orWhereIn('owner_id', Household::query()->select('id')));
                });
        });
    }

    /**
     * Mirrors the restore policy: personal owner, or list creator or manager within a household.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeRestorableBy(Builder $query, User $user): Builder
    {
        $householdIds = $user->households()->select('households.id');
        $managedIds = $user->households()->wherePivotIn('role', [HouseholdRole::Owner->value, HouseholdRole::Admin->value])->select('households.id');

        return $query->where(function (Builder $query) use ($user, $householdIds, $managedIds) {
            $query->where(fn (Builder $personal) => $personal->where('owner_type', 'user')->where('owner_id', $user->id))
                ->orWhere(fn (Builder $household) => $household->where('owner_type', 'household')
                    ->whereIn('owner_id', $householdIds)
                    ->where(fn (Builder $allowed) => $allowed->where('created_by', $user->id)->orWhereIn('owner_id', $managedIds)));
        });
    }

    public function isOwnedBy(Model $owner): bool
    {
        return $this->owner_type === $owner->getMorphClass() && $this->owner_id === $owner->getKey();
    }

    public function shareRoleFor(User $user): ?ListShareRole
    {
        return $this->shares()->where('user_id', $user->id)->first()?->role;
    }
}
