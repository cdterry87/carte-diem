<?php

namespace App\Policies;

use App\Models\ShoppingTrip;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ShoppingTripPolicy
{
    public function __construct(private readonly ShoppingListPolicy $lists) {}

    public function view(User $user, ShoppingTrip $trip): Response
    {
        return $this->lists->view($user, $trip->shoppingList);
    }

    /**
     * Amounts are spending data, which list guests never see or set.
     */
    public function manageAmounts(User $user, ShoppingTrip $trip): Response
    {
        return $this->lists->update($user, $trip->shoppingList);
    }

    public function run(User $user, ShoppingTrip $trip): Response
    {
        return $this->lists->runTrip($user, $trip->shoppingList);
    }
}
