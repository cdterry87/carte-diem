<?php

namespace App\Actions\Households;

use App\Events\InvitationAccepted;
use App\Exceptions\DomainConflictException;
use App\Models\AuditLog;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RespondToHouseholdInvitation
{
    public function accept(HouseholdInvitation $invitation, User $user): Household
    {
        return DB::transaction(function () use ($invitation, $user): Household {
            $invitation = $this->lockPending($invitation, $user);

            $household = $invitation->household;
            $household->addMember($user, $invitation->role);
            $invitation->update(['accepted_at' => now()]);
            AuditLog::record($user, 'household.invitation_accepted', $household, ['role' => $invitation->role->value]);
            InvitationAccepted::dispatch($household->uuid);

            return $household;
        });
    }

    public function decline(HouseholdInvitation $invitation, User $user): void
    {
        DB::transaction(function () use ($invitation, $user): void {
            $invitation = $this->lockPending($invitation, $user);
            $invitation->update(['declined_at' => now()]);
            AuditLog::record($user, 'household.invitation_declined', $invitation->household);
        });
    }

    private function lockPending(HouseholdInvitation $invitation, User $user): HouseholdInvitation
    {
        /** @var HouseholdInvitation $locked */
        $locked = HouseholdInvitation::query()->with('household')->whereKey($invitation->id)->lockForUpdate()->firstOrFail();

        if (! $locked->isFor($user) || ! $locked->isPending() || ! $locked->household()->exists()) {
            throw new DomainConflictException('This invitation is no longer valid.', 'invitation_invalid');
        }

        return $locked;
    }
}
