<?php

namespace App\Policies;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class HouseholdPolicy
{
    public function view(User $user, Household $household): Response
    {
        return $user->roleIn($household) !== null ? Response::allow() : Response::denyAsNotFound();
    }

    public function update(User $user, Household $household): Response
    {
        return $this->managed($user, $household);
    }

    public function invite(User $user, Household $household): Response
    {
        return $this->managed($user, $household);
    }

    public function manageMembers(User $user, Household $household): Response
    {
        return $this->managed($user, $household);
    }

    public function delete(User $user, Household $household): Response
    {
        return $this->owned($user, $household);
    }

    public function transfer(User $user, Household $household): Response
    {
        return $this->owned($user, $household);
    }

    private function managed(User $user, Household $household): Response
    {
        $role = $user->roleIn($household);

        return match (true) {
            $role === null => Response::denyAsNotFound(),
            $role->canManage() => Response::allow(),
            default => Response::deny(),
        };
    }

    private function owned(User $user, Household $household): Response
    {
        return match ($user->roleIn($household)) {
            null => Response::denyAsNotFound(),
            HouseholdRole::Owner => Response::allow(),
            default => Response::deny(),
        };
    }
}
