<?php

namespace App\Http\Controllers\Web;

use App\Http\Resources\OwnerResource;
use App\Services\OwnerResolver;
use App\Services\OwnerTimezone;
use App\Services\PriceHistory;
use App\Services\SpendingSummary;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SpendingController extends WebController
{
    public function index(Request $request, OwnerResolver $owners, SpendingSummary $summary): Response
    {
        $validated = $request->validate(['months' => ['sometimes', 'integer', Rule::in(SpendingSummary::PERIODS)]]);
        $household = $request->query('household');
        $owner = $owners->resolve($this->actor($request), is_string($household) ? 'household' : 'user', is_string($household) ? $household : null);

        return Inertia::render('spending/index', [
            'owner' => $this->props(OwnerResource::make($owner)),
            'summary' => $summary->for($owner, (int) ($validated['months'] ?? 12)),
            'periods' => SpendingSummary::PERIODS,
        ]);
    }

    public function prices(Request $request, OwnerResolver $owners, PriceHistory $history): Response
    {
        $validated = $request->validate(['product' => ['sometimes', 'uuid']]);
        $household = $request->query('household');
        $owner = $owners->resolve($this->actor($request), is_string($household) ? 'household' : 'user', is_string($household) ? $household : null);
        $product = isset($validated['product']) ? $history->product($owner, $validated['product']) : null;
        abort_if(isset($validated['product']) && $product === null, 404);

        return Inertia::render('spending/prices', [
            'owner' => $this->props(OwnerResource::make($owner)),
            'products' => $product === null ? $history->products($owner) : [],
            'product' => $product,
            'timezone' => OwnerTimezone::of($owner),
            'months' => PriceHistory::MONTHS,
        ]);
    }
}
