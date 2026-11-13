<?php

namespace App\Policies;

use App\Models\Household;
use App\Models\PurchaseTransaction;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PurchaseTransactionPolicy
{
    public function view(User $user, PurchaseTransaction $purchase): Response
    {
        $owner = $purchase->owner;

        return $owner !== null && $user->belongsToScope($owner) ? Response::allow() : Response::denyAsNotFound();
    }

    public function update(User $user, PurchaseTransaction $purchase): Response
    {
        return $this->view($user, $purchase);
    }

    /**
     * Any member can correct a purchase, but removing financial history takes its creator or a manager.
     */
    public function delete(User $user, PurchaseTransaction $purchase): Response
    {
        $response = $this->view($user, $purchase);

        if ($response->denied()) {
            return $response;
        }

        $owner = $purchase->owner;

        if (! $owner instanceof Household || $purchase->created_by === $user->id) {
            return Response::allow();
        }

        return $user->roleIn($owner)?->canManage() ? Response::allow() : Response::deny();
    }

    /**
     * Moving takes a purchase away from everyone in its current space, like deleting it does.
     */
    public function move(User $user, PurchaseTransaction $purchase): Response
    {
        return $this->delete($user, $purchase);
    }

    public function restore(User $user, PurchaseTransaction $purchase): Response
    {
        return $this->delete($user, $purchase);
    }
}
