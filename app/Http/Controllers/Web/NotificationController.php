<?php

namespace App\Http\Controllers\Web;

use App\Http\Resources\NotificationResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends WebController
{
    public function index(Request $request): Response
    {
        return Inertia::render('notifications/index', [
            'notifications' => $this->props(NotificationResource::collection(
                $this->actor($request)->notifications()->latest()->limit(100)->get()
            )),
        ]);
    }

    public function open(Request $request, string $notification): RedirectResponse
    {
        $model = $this->actor($request)->notifications()->whereKey($notification)->firstOrFail();
        $model->markAsRead();

        $url = $model->data['url'] ?? null;

        // Only paths on this site: "//host" and "/\host" would leave it.
        $local = is_string($url) && preg_match('#^/(?![/\\\\])#', $url) === 1;

        return $local ? redirect($url) : back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $this->actor($request)->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
