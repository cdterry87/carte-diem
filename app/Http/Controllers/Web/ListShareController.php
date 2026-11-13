<?php

namespace App\Http\Controllers\Web;

use App\Actions\Lists\ShareShoppingList;
use App\Enums\ListShareRole;
use App\Http\Requests\Lists\ShareListRequest;
use App\Models\ListShareInvitation;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ListShareController extends WebController
{
    public function store(ShareListRequest $request, ShoppingList $list, ShareShoppingList $share): RedirectResponse
    {
        Gate::authorize('share', $list);

        $share->invite($list, $this->actor($request), $request->string('email')->toString(), $request->role());

        return $this->backWith(__('Invitation sent.'));
    }

    public function update(ShareListRequest $request, ShoppingList $list, User $guest, ShareShoppingList $share): RedirectResponse
    {
        Gate::authorize('share', $list);

        $share->changeRole($list, $this->actor($request), $guest, $request->enum('role', ListShareRole::class) ?? ListShareRole::Viewer);

        return $this->backWith(__('Access updated.'));
    }

    public function destroy(Request $request, ShoppingList $list, User $guest, ShareShoppingList $share): RedirectResponse
    {
        $actor = $this->actor($request);

        if (! $actor->is($guest)) {
            Gate::authorize('share', $list);
        }

        $share->revoke($list, $actor, $guest);

        return $actor->is($guest) ? to_route('lists.index') : $this->backWith(__('Access removed.'));
    }

    public function revokeInvitation(Request $request, ShoppingList $list, ListShareInvitation $invitation, ShareShoppingList $share): RedirectResponse
    {
        Gate::authorize('share', $list);
        abort_unless($invitation->shopping_list_id === $list->id, 404);

        $share->revokeInvitation($invitation, $this->actor($request));

        return $this->backWith(__('Invitation revoked.'));
    }
}
