<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Inertia\Inertia;

abstract class WebController extends Controller
{
    /**
     * Fully resolve a resource, including nested ones, into plain page props.
     *
     * @return array<array-key, mixed>
     */
    protected function props(JsonResource $resource): array
    {
        /** @var array<array-key, mixed> */
        return json_decode((string) json_encode($resource), true);
    }

    protected function actor(Request $request): User
    {
        /** @var User */
        return $request->user();
    }

    protected function backWith(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
