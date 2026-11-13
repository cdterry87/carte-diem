<?php

namespace App\Models;

use App\Enums\HouseholdRole;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property HouseholdRole $role
 * @property Carbon $joined_at
 */
class Membership extends Pivot
{
    protected $table = 'household_user';

    public $incrementing = true;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => HouseholdRole::class,
            'joined_at' => 'datetime',
        ];
    }
}
