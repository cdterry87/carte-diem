<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Notifications\SaveNotificationSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\NotificationSettingsRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationSettingsController extends Controller
{
    public function edit(Request $request, SaveNotificationSettings $settings): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('settings/notifications', ['settings' => $settings->current($user)]);
    }

    public function update(NotificationSettingsRequest $request, SaveNotificationSettings $settings): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $settings->handle($user, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Notification settings saved.')]);

        return back();
    }
}
