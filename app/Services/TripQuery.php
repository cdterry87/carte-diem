<?php

namespace App\Services;

use App\Enums\TripStatus;
use App\Models\Household;
use App\Models\ShoppingList;
use App\Models\ShoppingTrip;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class TripQuery
{
    /**
     * Trips on lists the user can open, including soft-deleted lists.
     *
     * @return Builder<ShoppingTrip>
     */
    public function forUser(User $user, ?string $scope = null, ?TripStatus $status = null): Builder
    {
        $lists = ShoppingList::withTrashed()
            ->accessibleBy($user)
            ->when($scope === 'personal', fn (Builder $query) => $query->where('owner_type', 'user')->where('owner_id', $user->id))
            ->when($scope === 'shared', fn (Builder $query) => $query->whereHas('shares', fn (Builder $shares) => $shares->where('user_id', $user->id)))
            ->when($scope !== null && ! in_array($scope, ['personal', 'shared'], true), function (Builder $query) use ($scope) {
                $query->where('owner_type', 'household')->whereIn('owner_id', Household::query()->where('uuid', $scope)->select('id'));
            })
            ->select('id');

        return ShoppingTrip::query()
            ->whereIn('shopping_list_id', $lists)
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->with(['shoppingList.owner', 'store', 'completer'])
            ->withCount(['items', 'items as purchased_count' => fn (Builder $items) => $items->where('purchased', true)])
            ->orderByDesc('completed_at')
            ->orderByDesc('id');
    }
}
