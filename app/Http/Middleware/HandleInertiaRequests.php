<?php

namespace App\Http\Middleware;

use App\Http\Resources\UserResource;
use App\Models\Household;
use App\Support\DemoAccount;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'tagline' => config('app.tagline'),
            'taglineLong' => config('app.tagline_long'),
            'auth' => [
                'user' => $request->user() ? UserResource::make($request->user())->resolve($request) : null,
            ],
            'unreadNotifications' => fn () => $request->user()?->unreadNotifications()->count() ?? 0,
            'workspaces' => fn () => $request->user() ? [
                ['type' => 'user', 'uuid' => $request->user()->uuid, 'name' => __('Personal')],
                ...$request->user()->households()->orderBy('name')->get(['households.id', 'households.uuid', 'households.name'])
                    ->map(fn (Household $household) => ['type' => 'household', 'uuid' => $household->uuid, 'name' => $household->name])
                    ->all(),
            ] : [],
            'demo' => [
                'enabled' => DemoAccount::enabled(),
                'isDemoUser' => (bool) $request->user()?->is_demo,
            ],
            'canRegister' => ! DemoAccount::enabled(),
            'liveUpdates' => ! DemoAccount::enabled() && config('broadcasting.default') === 'reverb',
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
