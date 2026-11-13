<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CategoryPolicy
{
    public function update(User $user, Category $category): Response
    {
        if ($category->isSystem()) {
            return Response::deny();
        }

        $scope = $category->scope;

        return $scope !== null && $user->belongsToScope($scope) ? Response::allow() : Response::denyAsNotFound();
    }

    public function delete(User $user, Category $category): Response
    {
        return $this->update($user, $category);
    }
}
