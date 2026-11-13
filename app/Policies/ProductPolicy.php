<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProductPolicy
{
    public function view(User $user, Product $product): Response
    {
        $scope = $product->scope;

        return $scope !== null && $user->belongsToScope($scope) ? Response::allow() : Response::denyAsNotFound();
    }
}
