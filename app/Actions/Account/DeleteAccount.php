<?php

namespace App\Actions\Account;

use App\Exceptions\DomainConflictException;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\ListShareInvitation;
use App\Models\User;
use App\Services\OwnerDataPurger;
use Illuminate\Support\Facades\DB;

class DeleteAccount
{
    public function __construct(
        private readonly OwnerDataPurger $purger,
    ) {}

    /**
     * @param  bool  $force  Skip the shared-household check. Only the demo reset uses this, since
     *                       it rebuilds the demo household from scratch anyway.
     */
    public function handle(User $user, bool $force = false): void
    {
        DB::transaction(function () use ($user, $force): void {
            if (! $force) {
                $this->assertDeletable($user);
            }

            Household::withTrashed()->where('owner_user_id', $user->id)->get()->each(function (Household $household): void {
                $this->purger->purge($household);
                $household->forceDelete();
            });

            $this->purger->purge($user);

            $email = strtolower($user->email);
            HouseholdInvitation::query()->where('email', $email)->delete();
            ListShareInvitation::query()->where('email', $email)->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->notifications()->delete();

            $user->delete();
        });
    }

    /**
     * Run this before logging the user out, so a blocked deletion can still explain itself.
     */
    public function assertDeletable(User $user): void
    {
        $ownsSharedHousehold = Household::query()
            ->where('owner_user_id', $user->id)
            ->whereHas('members', fn ($members) => $members->whereKeyNot($user->id))
            ->exists();

        if ($ownsSharedHousehold) {
            throw new DomainConflictException('Transfer ownership of your shared households before deleting your account.', 'owner_required');
        }
    }
}
