<?php

namespace App\Actions\Households;

use App\Models\AuditLog;
use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteHousehold
{
    public function handle(Household $household, User $actor): void
    {
        DB::transaction(function () use ($household, $actor): void {
            $household->invitations()->pending()->update(['revoked_at' => now()]);
            $household->delete();
            AuditLog::record($actor, 'household.deleted', $household);
        });
    }
}
