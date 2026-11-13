<?php

namespace App\Services;

use App\Models\Household;
use App\Models\ListShare;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ScopeAudience
{
    /**
     * @return Collection<int, User>
     */
    public function forOwner(?Model $owner): Collection
    {
        return match (true) {
            $owner instanceof User => collect([$owner]),
            $owner instanceof Household => $owner->members()->get()->values(),
            default => collect(),
        };
    }

    /**
     * Everyone who can currently open the list, guests included.
     *
     * @return Collection<int, User>
     */
    public function forList(ShoppingList $list): Collection
    {
        if ($list->owner === null) {
            return collect();
        }

        $guests = $list->shares()->with('user')->get()->map(fn (ListShare $share): User => $share->user);

        return $this->forOwner($list->owner)->concat($guests)->unique('id')->values();
    }
}
