<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;

class DomainConflictException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(string $message, public readonly string $errorCode = 'conflict', public readonly array $meta = [])
    {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() && ! $request->inertia()) {
            return response()->json([
                'message' => $this->getMessage(),
                'code' => $this->errorCode,
                'meta' => (object) $this->meta,
            ], 409);
        }

        Inertia::flash('toast', ['type' => 'error', 'message' => $this->getMessage()]);

        return back();
    }
}
