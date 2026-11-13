<?php

use App\Models\Household;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

Broadcast::channel('user.{uuid}', fn (User $user, string $uuid): bool => $user->uuid === $uuid);

Broadcast::channel('list.{list}', fn (User $user, ShoppingList $list): bool => Gate::forUser($user)->allows('view', $list));

Broadcast::channel('household.{household}', fn (User $user, Household $household): bool => Gate::forUser($user)->allows('view', $household));
