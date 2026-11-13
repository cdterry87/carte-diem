<?php

namespace App\Http\Controllers\Web;

use App\Actions\Purchases\ManagePurchases;
use App\Http\Requests\Purchases\MovePurchaseRequest;
use App\Http\Requests\Purchases\PurchaseRequest;
use App\Http\Resources\OwnerResource;
use App\Http\Resources\PurchaseResource;
use App\Models\Category;
use App\Models\PurchaseTransaction;
use App\Models\Store;
use App\Services\OwnerResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends WebController
{
    private const DETAIL_RELATIONS = ['owner', 'trip', 'items.category'];

    public function index(Request $request, OwnerResolver $owners): Response
    {
        $owner = $this->workspace($request, $owners);
        $user = $this->actor($request);

        $purchases = $this->inScope(PurchaseTransaction::query(), $owner)
            ->with(['owner', 'trip'])
            ->withCount('items')
            ->orderByDesc('purchased_at')
            ->orderByDesc('id')
            ->simplePaginate(30)
            ->withQueryString();

        $deleted = $this->inScope(PurchaseTransaction::onlyTrashed(), $owner)
            ->with('owner')
            ->latest('deleted_at')
            ->limit(20)
            ->get()
            ->filter(fn (PurchaseTransaction $purchase): bool => Gate::forUser($user)->allows('restore', $purchase))
            ->values();

        return Inertia::render('purchases/index', [
            'owner' => $this->props(OwnerResource::make($owner)),
            'purchases' => $this->props(PurchaseResource::collection($purchases)),
            'pagination' => ['next' => $purchases->nextPageUrl(), 'previous' => $purchases->previousPageUrl()],
            'deleted' => $this->props(PurchaseResource::collection($deleted)),
        ]);
    }

    public function create(Request $request, OwnerResolver $owners): Response
    {
        $owner = $this->workspace($request, $owners);

        return Inertia::render('purchases/create', [
            'owner' => $this->props(OwnerResource::make($owner)),
            ...$this->formOptions($owner),
        ]);
    }

    public function store(PurchaseRequest $request, ManagePurchases $manage): RedirectResponse
    {
        $owner = $request->owner();

        $purchase = $manage->create($owner, $this->actor($request), $request->purchaseInput(), $request->input('idempotency_key'), $request->boolean('allow_duplicate'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Purchase saved.')]);

        return to_route('purchases.show', $purchase);
    }

    public function show(PurchaseTransaction $purchase): Response
    {
        Gate::authorize('view', $purchase);

        return Inertia::render('purchases/show', [
            'purchase' => $this->props(PurchaseResource::make($purchase->load(self::DETAIL_RELATIONS))),
        ]);
    }

    public function edit(PurchaseTransaction $purchase): Response
    {
        Gate::authorize('update', $purchase);
        /** @var Model $owner */
        $owner = $purchase->owner;

        return Inertia::render('purchases/edit', [
            'purchase' => $this->props(PurchaseResource::make($purchase->load(self::DETAIL_RELATIONS))),
            ...$this->formOptions($owner),
        ]);
    }

    public function update(PurchaseRequest $request, PurchaseTransaction $purchase, ManagePurchases $manage): RedirectResponse
    {
        Gate::authorize('update', $purchase);

        $manage->update($purchase, $this->actor($request), $request->purchaseInput(), $request->revision(), $request->boolean('allow_duplicate'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Purchase updated.')]);

        return to_route('purchases.show', $purchase);
    }

    public function move(MovePurchaseRequest $request, PurchaseTransaction $purchase, ManagePurchases $manage): RedirectResponse
    {
        Gate::authorize('move', $purchase);

        $revision = $request->has('revision') ? $request->integer('revision') : null;
        $manage->move($purchase, $request->owner(), $this->actor($request), $revision, $request->boolean('allow_duplicate'));

        return $this->backWith(__('Purchase moved.'));
    }

    public function destroy(Request $request, PurchaseTransaction $purchase, ManagePurchases $manage): RedirectResponse
    {
        Gate::authorize('delete', $purchase);

        $manage->delete($purchase, $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Purchase deleted. You can restore it from Purchases.')]);

        /** @var Model|null $owner */
        $owner = $purchase->owner;

        return to_route('purchases.index', $owner?->getMorphClass() === 'household' ? ['household' => $owner->getAttribute('uuid')] : []);
    }

    public function restore(Request $request, PurchaseTransaction $purchase, ManagePurchases $manage): RedirectResponse
    {
        Gate::authorize('restore', $purchase);

        $manage->restore($purchase, $this->actor($request));

        return $this->backWith(__('Purchase restored.'));
    }

    private function workspace(Request $request, OwnerResolver $owners): Model
    {
        $household = $request->query('household');

        return $owners->resolve($this->actor($request), is_string($household) ? 'household' : 'user', is_string($household) ? $household : null);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function inScope(Builder $query, Model $owner): Builder
    {
        return $query->where('owner_type', $owner->getMorphClass())->where('owner_id', $owner->getKey());
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Model $owner): array
    {
        return [
            'currency' => PurchaseTransaction::currencyFor($owner),
            'stores' => Store::query()->forOwner($owner)->orderBy('name')->pluck('name'),
            'categories' => Category::query()->availableTo($owner)->get(['uuid', 'name']),
        ];
    }
}
