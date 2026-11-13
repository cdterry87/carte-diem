<?php

namespace App\Actions\Households;

use App\Enums\HouseholdRole;
use App\Models\AuditLog;
use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateHousehold
{
    /**
     * @param  array{name: string, timezone?: string|null, currency_code?: string|null}  $attributes
     */
    public function handle(User $user, array $attributes): Household
    {
        return DB::transaction(function () use ($user, $attributes): Household {
            $household = Household::query()->create([
                'name' => $attributes['name'],
                'owner_user_id' => $user->id,
                'timezone' => $attributes['timezone'] ?? $user->timezone,
                'currency_code' => $attributes['currency_code'] ?? 'USD',
            ]);

            $household->addMember($user, HouseholdRole::Owner);
            AuditLog::record($user, 'household.created', $household);

            return $household;
        });
    }
}
