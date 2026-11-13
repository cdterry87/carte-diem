<?php

namespace App\Actions\Lists;

use App\Enums\ListShareRole;
use App\Exceptions\DomainConflictException;
use App\Models\AuditLog;
use App\Models\Household;
use App\Models\ListShareInvitation;
use App\Models\ShoppingList;
use App\Models\User;
use App\Notifications\InvitationReceived;
use App\Notifications\ListShareInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class ShareShoppingList
{
    public const EXPIRES_IN_DAYS = 14;

    public function invite(ShoppingList $list, User $inviter, string $email, ListShareRole $role): ListShareInvitation
    {
        $email = strtolower(trim($email));
        $token = Str::random(48);

        $invitation = DB::transaction(function () use ($list, $inviter, $email, $role, $token): ListShareInvitation {
            ShoppingList::query()->whereKey($list->id)->lockForUpdate()->first();

            if ($this->alreadyHasAccess($list, $email)) {
                throw new DomainConflictException('That person can already open this list.', 'already_has_access');
            }

            $list->shareInvitations()->pending()->where('email', $email)->update(['revoked_at' => now()]);

            $invitation = $list->shareInvitations()->create([
                'email' => $email,
                'role' => $role,
                'token_hash' => hash('sha256', $token),
                'invited_by' => $inviter->id,
                'expires_at' => now()->addDays(self::EXPIRES_IN_DAYS),
            ]);

            AuditLog::record($inviter, 'list.share_invited', $list, ['invitation' => $invitation->uuid, 'role' => $role->value]);

            return $invitation;
        });

        Notification::route('mail', $email)->notify(new ListShareInvitationNotification($list->title, $inviter->name, $token));

        User::query()->where('email', $email)->first()?->notify(new InvitationReceived(
            __(':name shared the list :list with you', ['name' => $inviter->name, 'list' => $list->title]),
        ));

        return $invitation;
    }

    public function accept(ListShareInvitation $invitation, User $user): ShoppingList
    {
        return DB::transaction(function () use ($invitation, $user): ShoppingList {
            $locked = $this->lockPending($invitation, $user);
            $list = $locked->shoppingList;

            $list->shares()->updateOrCreate(['user_id' => $user->id], ['role' => $locked->role, 'invited_by' => $locked->invited_by]);
            $locked->update(['accepted_at' => now()]);
            AuditLog::record($user, 'list.share_accepted', $list, ['role' => $locked->role->value]);

            return $list;
        });
    }

    public function decline(ListShareInvitation $invitation, User $user): void
    {
        DB::transaction(function () use ($invitation, $user): void {
            $locked = $this->lockPending($invitation, $user);
            $locked->update(['declined_at' => now()]);
            AuditLog::record($user, 'list.share_declined', $locked->shoppingList);
        });
    }

    public function changeRole(ShoppingList $list, User $actor, User $guest, ListShareRole $role): void
    {
        $list->shares()->where('user_id', $guest->id)->firstOrFail()->update(['role' => $role]);
        AuditLog::record($actor, 'list.share_role_changed', $list, ['guest' => $guest->uuid, 'role' => $role->value]);
    }

    public function revoke(ShoppingList $list, User $actor, User $guest): void
    {
        $list->shares()->where('user_id', $guest->id)->firstOrFail()->delete();
        AuditLog::record($actor, 'list.share_revoked', $list, ['guest' => $guest->uuid]);
    }

    public function revokeInvitation(ListShareInvitation $invitation, User $actor): void
    {
        DB::transaction(function () use ($invitation, $actor): void {
            /** @var ListShareInvitation $locked */
            $locked = ListShareInvitation::query()->with('shoppingList')->whereKey($invitation->id)->lockForUpdate()->firstOrFail();

            if ($locked->isPending()) {
                $locked->update(['revoked_at' => now()]);
                AuditLog::record($actor, 'list.share_invitation_revoked', $locked->shoppingList, ['invitation' => $locked->uuid]);
            }
        });
    }

    private function alreadyHasAccess(ShoppingList $list, string $email): bool
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            return false;
        }

        $owner = $list->owner;

        return ($owner instanceof Household || $owner instanceof User) && $user->belongsToScope($owner)
            || $list->shares()->where('user_id', $user->id)->exists();
    }

    private function lockPending(ListShareInvitation $invitation, User $user): ListShareInvitation
    {
        /** @var ListShareInvitation $locked */
        $locked = ListShareInvitation::query()->with('shoppingList')->whereKey($invitation->id)->lockForUpdate()->firstOrFail();

        if (! $locked->isFor($user) || ! $locked->isPending() || ! $locked->shoppingList()->exists()) {
            throw new DomainConflictException('This invitation is no longer valid.', 'invitation_invalid');
        }

        return $locked;
    }
}
