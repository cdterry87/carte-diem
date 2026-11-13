<?php

namespace App\Http\Controllers\Web;

use App\Actions\Trips\CancelShoppingTrip;
use App\Actions\Trips\CompleteShoppingTrip;
use App\Actions\Trips\StartShoppingTrip;
use App\Actions\Trips\UpdateTripItem;
use App\Enums\TripStatus;
use App\Http\Requests\Lists\StartTripRequest;
use App\Http\Requests\Lists\TripItemRequest;
use App\Http\Resources\TripResource;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\ShoppingTrip;
use App\Models\ShoppingTripItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TripController extends WebController
{
    public function store(StartTripRequest $request, ShoppingList $list, StartShoppingTrip $start): RedirectResponse
    {
        Gate::authorize('runTrip', $list);

        $active = ShoppingTrip::query()->where('shopping_list_id', $list->id)->where('status', TripStatus::Active)->first();

        if ($active !== null) {
            return to_route('trips.show', $active);
        }

        return to_route('trips.show', $start->handle($list, $this->actor($request), $request->store(), $request->itemUuids(), $request->boolean('no_store')));
    }

    public function show(ShoppingTrip $trip): Response
    {
        Gate::authorize('view', $trip);

        return Inertia::render('trips/show', [
            'trip' => $this->props(TripResource::make($trip->load([
                'shoppingList.owner',
                'store',
                'starter',
                'completer',
                'purchase',
                'items' => fn ($items) => $items->with('checker')->orderBy('sort_order'),
            ]))),
        ]);
    }

    public function updateItem(TripItemRequest $request, ShoppingTrip $trip, string $item, UpdateTripItem $update): RedirectResponse
    {
        Gate::authorize('run', $trip);
        $request->authorizeAmounts($trip);

        /** @var ShoppingTripItem $model */
        $model = $trip->items()->where('uuid', $item)->firstOrFail();
        $revision = $request->has('revision') ? $request->integer('revision') : null;

        $update->handle($model, $this->actor($request), $request->tripAttributes(), $revision);

        return back();
    }

    public function updateAmount(Request $request, ShoppingTrip $trip, UpdateTripItem $update): RedirectResponse
    {
        Gate::authorize('run', $trip);
        Gate::authorize('manageAmounts', $trip);

        $validated = $request->validate(['total_minor' => ['present', 'nullable', 'integer', 'min:0', 'max:99999999999']]);

        $update->setTotal($trip, $validated['total_minor']);

        return back();
    }

    public function includeItem(Request $request, ShoppingTrip $trip, UpdateTripItem $update): RedirectResponse
    {
        Gate::authorize('run', $trip);

        $validated = $request->validate(['item' => ['required', 'uuid']]);
        /** @var ShoppingListItem $listItem */
        $listItem = $trip->shoppingList->items()->where('uuid', $validated['item'])->firstOrFail();

        $update->include($trip, $listItem);

        return back();
    }

    public function complete(Request $request, ShoppingTrip $trip, CompleteShoppingTrip $complete): RedirectResponse
    {
        Gate::authorize('run', $trip);

        $complete->handle($trip, $this->actor($request));

        return $this->backWith(__('Trip completed.'));
    }

    public function cancel(ShoppingTrip $trip, CancelShoppingTrip $cancel): RedirectResponse
    {
        Gate::authorize('run', $trip);

        $cancel->handle($trip);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Trip cancelled.')]);

        return to_route('lists.show', $trip->shoppingList);
    }
}
