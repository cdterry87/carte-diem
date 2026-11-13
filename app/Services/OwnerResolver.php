<?php

namespace App\Services;

use App\Models\Household;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class OwnerResolver
{
    /**
     * Resolve an owner reference the user belongs to, or fail with 404.
     */
    public function resolve(User $user, string $type, ?string $uuid): Model
    {
        $owner = match ($type) {
            'user' => $uuid === null || $uuid === $user->uuid ? $user : null,
            'household' => Household::query()->where('uuid', $uuid)->first(),
            default => null,
        };

        abort_unless($owner !== null && $user->belongsToScope($owner), 404);

        return $owner;
    }
}
