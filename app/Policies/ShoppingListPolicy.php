<?php

namespace App\Policies;

use App\Enums\ListShareRole;
use App\Models\Household;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ShoppingListPolicy
{
    public function view(User $user, ShoppingList $list): Response
    {
        return $this->inScope($user, $list) || $this->liveShareRole($user, $list) !== null
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * List metadata and archiving belong to the owner scope, never guests.
     */
    public function update(User $user, ShoppingList $list): Response
    {
        return $this->scopeOnly($user, $list);
    }

    public function duplicate(User $user, ShoppingList $list): Response
    {
        return $this->scopeOnly($user, $list);
    }

    public function editItems(User $user, ShoppingList $list): Response
    {
        if ($this->inScope($user, $list) || $this->liveShareRole($user, $list) === ListShareRole::Editor) {
            return Response::allow();
        }

        return $this->view($user, $list)->allowed() ? Response::deny() : Response::denyAsNotFound();
    }

    public function runTrip(User $user, ShoppingList $list): Response
    {
        return $this->editItems($user, $list);
    }

    public function share(User $user, ShoppingList $list): Response
    {
        return $this->creatorOrManager($user, $list);
    }

    public function delete(User $user, ShoppingList $list): Response
    {
        return $this->creatorOrManager($user, $list);
    }

    public function restore(User $user, ShoppingList $list): Response
    {
        return $this->creatorOrManager($user, $list);
    }

    private function inScope(User $user, ShoppingList $list): bool
    {
        $owner = $list->owner;

        return $owner !== null && $user->belongsToScope($owner);
    }

    /**
     * A share stops granting access once its household has been deleted.
     */
    private function liveShareRole(User $user, ShoppingList $list): ?ListShareRole
    {
        return $list->owner === null ? null : $list->shareRoleFor($user);
    }

    private function scopeOnly(User $user, ShoppingList $list): Response
    {
        if ($this->inScope($user, $list)) {
            return Response::allow();
        }

        return $this->view($user, $list)->allowed() ? Response::deny() : Response::denyAsNotFound();
    }

    private function creatorOrManager(User $user, ShoppingList $list): Response
    {
        $response = $this->scopeOnly($user, $list);

        if ($response->denied()) {
            return $response;
        }

        $owner = $list->owner;

        if (! $owner instanceof Household || $list->created_by === $user->id) {
            return Response::allow();
        }

        return $user->roleIn($owner)?->canManage() ? Response::allow() : Response::deny();
    }
}
