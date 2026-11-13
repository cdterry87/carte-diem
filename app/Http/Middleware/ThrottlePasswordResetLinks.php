<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fortify does not limit reset link requests per visitor, only per account, so one visitor
 * could otherwise send reset emails to many accounts.
 */
class ThrottlePasswordResetLinks
{
    public function __construct(private readonly ThrottleRequests $throttle) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->route()?->getName() !== 'password.email') {
            return $next($request);
        }

        return $this->throttle->handle($request, $next, 'password-reset-links');
    }
}
