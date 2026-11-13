<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A likely duplicate the user can review and override by resending with allow_duplicate.
 */
class SuspectedDuplicateException extends DomainConflictException
{
    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() && ! $request->inertia()) {
            return parent::render($request);
        }

        return back()->withErrors(['allow_duplicate' => $this->getMessage()]);
    }
}
