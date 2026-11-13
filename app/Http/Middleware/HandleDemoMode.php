<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\DemoAccount;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Demo mode closes sign-up, and keeps visitors sharing the demo account from locking each
 * other out, sending email to strangers, or wrecking the demo household and account name
 * that everyone else sees. Everything else they change is undone by the daily demo:reset.
 */
class HandleDemoMode
{
    /**
     * Routes the demo account may not use.
     */
    private const LOCKED_FOR_DEMO_USER = [
        'user-password.update',
        'profile.destroy',
        'two-factor.enable',
        'two-factor.confirm',
        'two-factor.disable',
        'two-factor.regenerate-recovery-codes',
        'households.invitations.store',
        'lists.shares.store',
        'households.update',
        'households.destroy',
        'households.leave',
        'households.transfer',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route()?->getName();

        if (DemoAccount::enabled() && in_array($route, ['register', 'register.store'], true)) {
            abort(404);
        }

        // Answer as if a link was sent, without sending one, so nobody can take over the demo account.
        if ($route === 'password.email' && DemoAccount::configured()
            && strtolower($request->string('email')->trim()->toString()) === strtolower((string) config('demo.email'))) {
            return back()->with('status', __(Password::RESET_LINK_SENT));
        }

        $user = $request->user();

        if ($user instanceof User && $user->is_demo) {
            $changesEmail = $route === 'profile.update' && $request->has('email')
                && strtolower($request->string('email')->trim()->toString()) !== strtolower($user->email);
            $changesName = $route === 'profile.update' && $request->has('name')
                && $request->string('name')->trim()->toString() !== $user->name;

            if ($changesEmail || $changesName || in_array($route, self::LOCKED_FOR_DEMO_USER, true)) {
                Inertia::flash('toast', ['type' => 'error', 'message' => __('That is turned off for the shared demo account.')]);

                return back();
            }
        }

        return $next($request);
    }
}
