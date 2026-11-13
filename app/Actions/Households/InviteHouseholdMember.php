<?php

namespace App\Actions\Households;

use App\Enums\HouseholdRole;
use App\Exceptions\DomainConflictException;
use App\Models\AuditLog;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\User;
use App\Notifications\HouseholdInvitationNotification;
use App\Notifications\InvitationReceived;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class InviteHouseholdMember
{
    public const EXPIRES_IN_DAYS = 14;

    public function handle(Household $household, User $inviter, string $email, HouseholdRole $role): HouseholdInvitation
    {
        $email = strtolower(trim($email));
        $token = Str::random(48);

        $invitation = DB::transaction(function () use ($household, $inviter, $email, $role, $token): HouseholdInvitation {
            Household::query()->whereKey($household->id)->lockForUpdate()->first();

            $alreadyMember = $household->members()->where('users.email', $email)->exists();

            if ($alreadyMember) {
                throw new DomainConflictException('That person is already a member of this household.', 'already_member');
            }

            $household->invitations()->pending()->where('email', $email)->update(['revoked_at' => now()]);

            $invitation = $household->invitations()->create([
                'email' => $email,
                'role' => $role,
                'token_hash' => hash('sha256', $token),
                'invited_by' => $inviter->id,
                'expires_at' => now()->addDays(self::EXPIRES_IN_DAYS),
            ]);

            AuditLog::record($inviter, 'household.member_invited', $household, ['invitation' => $invitation->uuid, 'role' => $role->value]);

            return $invitation;
        });

        Notification::route('mail', $email)->notify(new HouseholdInvitationNotification($household->name, $inviter->name, $token));

        User::query()->where('email', $email)->first()?->notify(new InvitationReceived(
            __(':name invited you to join :household', ['name' => $inviter->name, 'household' => $household->name]),
        ));

        return $invitation;
    }

    public function revoke(HouseholdInvitation $invitation, User $actor): void
    {
        DB::transaction(function () use ($invitation, $actor): void {
            /** @var HouseholdInvitation $locked */
            $locked = HouseholdInvitation::query()->with('household')->whereKey($invitation->id)->lockForUpdate()->firstOrFail();

            if ($locked->isPending()) {
                $locked->update(['revoked_at' => now()]);
                AuditLog::record($actor, 'household.invitation_revoked', $locked->household, ['invitation' => $locked->uuid]);
            }
        });
    }
}
