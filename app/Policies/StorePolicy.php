<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class StorePolicy
{
    public function view(User $user, Store $store): Response
    {
        $scope = $store->scope;

        return $scope !== null && $user->belongsToScope($scope) ? Response::allow() : Response::denyAsNotFound();
    }

    public function update(User $user, Store $store): Response
    {
        return $this->view($user, $store);
    }

    public function delete(User $user, Store $store): Response
    {
        return $this->view($user, $store);
    }
}
