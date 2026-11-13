<?php

namespace App\Http\Controllers\Web;

use App\Enums\DashboardNotice;
use App\Enums\TripStatus;
use App\Http\Resources\HouseholdInvitationResource;
use App\Http\Resources\ListShareInvitationResource;
use App\Http\Resources\PurchaseResource;
use App\Http\Resources\RecommendationResource;
use App\Http\Resources\ShoppingListResource;
use App\Http\Resources\TripResource;
use App\Models\HouseholdInvitation;
use App\Models\ListShareInvitation;
use App\Models\PurchaseTransaction;
use App\Models\User;
use App\Services\ListQuery;
use App\Services\Recommendations;
use App\Services\TripQuery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends WebController
{
    public function __invoke(Request $request, ListQuery $lists, TripQuery $trips, Recommendations $recommendations): Response
    {
        $user = $this->actor($request);
        $email = strtolower($user->email);

        return Inertia::render('dashboard', [
            'notice' => $this->notice($user)?->value,
            'suggestions' => $this->props(RecommendationResource::collection(
                collect([$user, ...$user->households()->get()->all()])
                    ->flatMap(fn (Model $owner) => $recommendations->forOwner($owner, $user, limit: 3))
                    ->values()
            )),
            'lists' => $this->props(ShoppingListResource::collection($lists->forUser($user)->limit(6)->get())),
            'activeTrips' => $this->props(TripResource::collection($trips->forUser($user, status: TripStatus::Active)->limit(5)->get())),
            'recentPurchases' => $this->props(PurchaseResource::collection(
                PurchaseTransaction::query()->visibleTo($user)->with('owner')->withCount('items')->latest('purchased_at')->limit(5)->get()
            )),
            'householdInvitations' => $this->props(HouseholdInvitationResource::collection(
                HouseholdInvitation::query()->pending()->with('household')->where('email', $email)->whereHas('household')->get()
            )),
            'listInvitations' => $this->props(ListShareInvitationResource::collection(
                ListShareInvitation::query()->pending()->with('shoppingList')->where('email', $email)->whereHas('shoppingList')->get()
            )),
        ]);
    }

    /**
     * Someone outside any household is invited to start one.
     */
    private function notice(User $user): ?DashboardNotice
    {
        if ($user->hasDismissed(DashboardNotice::Household) || $user->households()->exists()) {
            return null;
        }

        return DashboardNotice::Household;
    }
}
