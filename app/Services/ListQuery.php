<?php

namespace App\Services;

use App\Enums\ListStatus;
use App\Models\Household;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ListQuery
{
    /**
     * Accessible lists, filtered by scope: personal, shared, or a household UUID.
     *
     * @return Builder<ShoppingList>
     */
    public function forUser(User $user, ?string $scope = null, ?string $status = null, ?string $search = null): Builder
    {
        return ShoppingList::query()
            ->when(
                $status === 'deleted',
                fn (Builder $query) => $query->onlyTrashed()->restorableBy($user),
                fn (Builder $query) => $query->accessibleBy($user)->where('status', $status ?? ListStatus::Active->value),
            )
            ->with(['owner', 'defaultStore'])
            ->withCount(['items', 'neededItems'])
            ->when($scope === 'personal', fn (Builder $query) => $query->where('owner_type', 'user')->where('owner_id', $user->id))
            ->when($scope === 'shared', fn (Builder $query) => $query->whereHas('shares', fn (Builder $shares) => $shares->where('user_id', $user->id)))
            ->when($scope !== null && ! in_array($scope, ['personal', 'shared'], true), function (Builder $query) use ($scope) {
                $query->where('owner_type', 'household')->whereIn('owner_id', Household::query()->where('uuid', $scope)->select('id'));
            })
            ->when($search !== null && $search !== '', fn (Builder $query) => $query->where('title', 'like', '%'.addcslashes((string) $search, '%_\\').'%'))
            ->latest('updated_at');
    }
}
