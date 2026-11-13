<?php

namespace App\Models;

use App\Enums\DashboardNotice;
use App\Enums\HouseholdRole;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string $timezone
 * @property string $locale
 * @property string|null $quiet_start
 * @property string|null $quiet_end
 * @property array<int, string>|null $dismissed_notices
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'timezone', 'locale', 'quiet_start', 'quiet_end'])]
#[Hidden(['id', 'password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPublicUuid, Notifiable, TwoFactorAuthenticatable;

    /**
     * @var array<string, string|null>
     */
    protected $attributes = [
        'timezone' => 'UTC',
        'locale' => 'en',
        'quiet_start' => null,
        'quiet_end' => null,
        'two_factor_secret' => null,
        'two_factor_recovery_codes' => null,
        'two_factor_confirmed_at' => null,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'dismissed_notices' => 'array',
        ];
    }

    public function hasDismissed(DashboardNotice $notice): bool
    {
        return in_array($notice->value, $this->dismissed_notices ?? [], true);
    }

    public function dismiss(DashboardNotice $notice): void
    {
        if ($this->hasDismissed($notice)) {
            return;
        }

        $this->forceFill(['dismissed_notices' => [...$this->dismissed_notices ?? [], $notice->value]])->save();
    }

    /**
     * @return BelongsToMany<Household, $this, Membership>
     */
    public function households(): BelongsToMany
    {
        return $this->belongsToMany(Household::class)
            ->using(Membership::class)
            ->withPivot(['role', 'joined_at']);
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function favoriteProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_favorites');
    }

    /**
     * @return BelongsToMany<ShoppingList, $this>
     */
    public function favoriteLists(): BelongsToMany
    {
        return $this->belongsToMany(ShoppingList::class, 'shopping_list_favorites');
    }

    public function receivesBroadcastNotificationsOn(): string
    {
        return 'user.'.$this->uuid;
    }

    public function roleIn(Household $household): ?HouseholdRole
    {
        $membership = Membership::query()
            ->where('household_id', $household->id)
            ->where('user_id', $this->id)
            ->first();

        return $membership?->role;
    }

    /**
     * Whether this user may act within a personal or household owner scope.
     */
    public function belongsToScope(Model $owner): bool
    {
        return match (true) {
            $owner instanceof self => $owner->is($this),
            $owner instanceof Household => $owner->deleted_at === null && $this->roleIn($owner) !== null,
            default => false,
        };
    }
}
