<?php

namespace App\Actions\Households;

use App\Enums\HouseholdRole;
use App\Exceptions\DomainConflictException;
use App\Models\AuditLog;
use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ManageHouseholdMembers
{
    public function changeRole(Household $household, User $actor, User $member, HouseholdRole $role): void
    {
        if ($role === HouseholdRole::Owner) {
            throw new DomainConflictException('Use ownership transfer to change the owner.', 'owner_required');
        }

        DB::transaction(function () use ($household, $actor, $member, $role): void {
            Household::query()->whereKey($household->id)->lockForUpdate()->first();
            $this->assertNotOwner($household, $member);

            $household->members()->updateExistingPivot($member->id, ['role' => $role->value]);
            AuditLog::record($actor, 'household.member_role_changed', $household, ['member' => $member->uuid, 'role' => $role->value]);
        });
    }

    public function remove(Household $household, User $actor, User $member): void
    {
        DB::transaction(function () use ($household, $actor, $member): void {
            Household::query()->whereKey($household->id)->lockForUpdate()->first();
            $this->assertNotOwner($household, $member);

            $household->members()->detach($member->id);
            AuditLog::record($actor, $actor->is($member) ? 'household.member_left' : 'household.member_removed', $household, ['member' => $member->uuid]);
        });
    }

    public function transferOwnership(Household $household, User $actor, User $newOwner): void
    {
        DB::transaction(function () use ($household, $actor, $newOwner): void {
            Household::query()->whereKey($household->id)->lockForUpdate()->first();

            $household->members()->updateExistingPivot($household->owner_user_id, ['role' => HouseholdRole::Admin->value]);
            $household->members()->updateExistingPivot($newOwner->id, ['role' => HouseholdRole::Owner->value]);
            $household->update(['owner_user_id' => $newOwner->id]);
            AuditLog::record($actor, 'household.ownership_transferred', $household, ['new_owner' => $newOwner->uuid]);
        });
    }

    private function assertNotOwner(Household $household, User $member): void
    {
        if ($member->roleIn($household) === HouseholdRole::Owner) {
            throw new DomainConflictException('Transfer ownership before the owner leaves or changes role.', 'owner_required');
        }
    }
}
