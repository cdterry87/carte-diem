<?php

namespace App\Models;

use App\Enums\HouseholdRole;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\HouseholdFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property int $owner_user_id
 * @property string $timezone
 * @property string $currency_code
 * @property Carbon|null $deleted_at
 * @property-read User $owner
 */
#[Fillable(['name', 'owner_user_id', 'timezone', 'currency_code'])]
class Household extends Model
{
    /** @use HasFactory<HouseholdFactory> */
    use HasFactory, HasPublicUuid, SoftDeletes;

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return BelongsToMany<User, $this, Membership>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(Membership::class)
            ->withPivot(['role', 'joined_at']);
    }

    /**
     * @return HasMany<HouseholdInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(HouseholdInvitation::class);
    }

    public function addMember(User $user, HouseholdRole $role): void
    {
        $this->members()->syncWithoutDetaching([
            $user->id => ['role' => $role->value, 'joined_at' => now()],
        ]);
    }
}
