<?php

namespace App\Http\Controllers\Web;

use App\Actions\Recommendations\DismissRecommendation;
use App\Actions\Recommendations\SaveStapleRule;
use App\Http\Requests\Lists\StapleRuleRequest;
use App\Http\Resources\OwnerResource;
use App\Http\Resources\StapleRuleResource;
use App\Models\Product;
use App\Models\RecommendationDismissal;
use App\Models\StapleRule;
use App\Services\OwnerResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StapleController extends WebController
{
    public function index(Request $request, OwnerResolver $owners): Response
    {
        $household = $request->query('household');
        $user = $this->actor($request);
        $owner = $owners->resolve($user, is_string($household) ? 'household' : 'user', is_string($household) ? $household : null);

        return Inertia::render('staples/index', [
            'owner' => $this->props(OwnerResource::make($owner)),
            'staples' => $this->props(StapleRuleResource::collection(
                StapleRule::query()->where('owner_type', $owner->getMorphClass())->where('owner_id', $owner->getKey())->with('product')->get()
            )),
            'muted' => RecommendationDismissal::query()
                ->where('user_id', $user->id)
                ->where('reason', RecommendationDismissal::MUTE)
                ->whereHas('product', fn ($products) => $products->forOwner($owner))
                ->with('product')
                ->get()
                ->map(fn (RecommendationDismissal $dismissal): array => [
                    'product' => $dismissal->product->uuid,
                    'name' => $dismissal->product->canonical_name,
                ]),
        ]);
    }

    public function store(StapleRuleRequest $request, SaveStapleRule $save): RedirectResponse
    {
        /** @var array{cadence_days?: int|null, prediction_enabled?: bool, reminder_enabled?: bool} $attributes */
        $attributes = $request->ruleAttributes();

        $save->create($request->owner(), $request->string('name')->toString(), $attributes);

        return $this->backWith(__('Staple added.'));
    }

    public function update(StapleRuleRequest $request, StapleRule $staple): RedirectResponse
    {
        Gate::authorize('manage', $staple);

        $staple->update($request->ruleAttributes());

        return back();
    }

    public function destroy(StapleRule $staple): RedirectResponse
    {
        Gate::authorize('manage', $staple);

        $staple->delete();

        return $this->backWith(__('Staple removed.'));
    }

    public function dismiss(Request $request, Product $product, DismissRecommendation $dismiss): RedirectResponse
    {
        Gate::authorize('view', $product);

        $validated = $request->validate(['action' => ['required', Rule::in([RecommendationDismissal::DISMISS, RecommendationDismissal::SNOOZE, RecommendationDismissal::MUTE])]]);

        $dismiss->handle($this->actor($request), $product, $validated['action']);

        return back();
    }

    public function lift(Request $request, Product $product, DismissRecommendation $dismiss): RedirectResponse
    {
        Gate::authorize('view', $product);

        $dismiss->lift($this->actor($request), $product);

        return back();
    }
}
