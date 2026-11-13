<?php

namespace App\Policies;

use App\Models\StapleRule;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class StapleRulePolicy
{
    public function manage(User $user, StapleRule $rule): Response
    {
        $owner = $rule->owner;

        return $owner !== null && $user->belongsToScope($owner) ? Response::allow() : Response::denyAsNotFound();
    }
}
