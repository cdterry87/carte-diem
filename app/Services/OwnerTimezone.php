<?php

namespace App\Services;

use App\Models\Household;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class OwnerTimezone
{
    public static function of(Model $owner): string
    {
        return match (true) {
            $owner instanceof Household, $owner instanceof User => $owner->timezone,
            default => 'UTC',
        };
    }
}
