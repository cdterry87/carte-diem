<?php

namespace App\Http\Controllers\Web;

use App\Actions\Households\RespondToHouseholdInvitation;
use App\Actions\Lists\ShareShoppingList;
use App\Models\HouseholdInvitation;
use App\Models\ListShareInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends WebController
{
    /**
     * Every failure renders the same page, so a token reveals nothing it should not.
     */
    public function showHousehold(Request $request, string $token): Response
    {
        $invitation = HouseholdInvitation::query()->with(['household', 'inviter'])->where('token_hash', hash('sha256', $token))->first();
        $valid = $invitation !== null && $invitation->isPending() && $invitation->isFor($this->actor($request)) && $invitation->household()->exists();

        return Inertia::render('invitations/show', [
            'invitation' => $valid ? [
                'type' => 'household',
                'uuid' => $invitation->uuid,
                'name' => $invitation->household->name,
                'inviter' => $invitation->inviter?->name,
                'role' => $invitation->role->value,
            ] : null,
        ]);
    }

    public function showList(Request $request, string $token): Response
    {
        $invitation = ListShareInvitation::query()->with('shoppingList')->where('token_hash', hash('sha256', $token))->first();
        $valid = $invitation !== null && $invitation->isPending() && $invitation->isFor($this->actor($request)) && $invitation->shoppingList()->exists();

        return Inertia::render('invitations/show', [
            'invitation' => $valid ? [
                'type' => 'list',
                'uuid' => $invitation->uuid,
                'name' => $invitation->shoppingList->title,
                'inviter' => null,
                'role' => $invitation->role->value,
            ] : null,
        ]);
    }

    public function acceptHousehold(Request $request, HouseholdInvitation $invitation, RespondToHouseholdInvitation $respond): RedirectResponse
    {
        abort_unless($invitation->isFor($this->actor($request)), 404);

        return to_route('households.show', $respond->accept($invitation, $this->actor($request)));
    }

    public function declineHousehold(Request $request, HouseholdInvitation $invitation, RespondToHouseholdInvitation $respond): RedirectResponse
    {
        abort_unless($invitation->isFor($this->actor($request)), 404);

        $respond->decline($invitation, $this->actor($request));

        return to_route('dashboard');
    }

    public function acceptList(Request $request, ListShareInvitation $invitation, ShareShoppingList $share): RedirectResponse
    {
        abort_unless($invitation->isFor($this->actor($request)), 404);

        return to_route('lists.show', $share->accept($invitation, $this->actor($request)));
    }

    public function declineList(Request $request, ListShareInvitation $invitation, ShareShoppingList $share): RedirectResponse
    {
        abort_unless($invitation->isFor($this->actor($request)), 404);

        $share->decline($invitation, $this->actor($request));

        return to_route('dashboard');
    }
}
