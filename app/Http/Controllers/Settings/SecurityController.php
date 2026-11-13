<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Fortify;

class SecurityController extends Controller
{
    /**
     * Show the user's security settings page.
     *
     * Two-factor sign-in is optional. While it is being set up, the page carries the QR code and
     * setup key. Recovery codes are sent only right after they are made, or when asked for.
     */
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $enabled = $user->hasEnabledTwoFactorAuthentication();
        $pending = ! $enabled && $user->two_factor_secret !== null;
        $justMade = in_array($request->session()->get('status'), [
            Fortify::TWO_FACTOR_AUTHENTICATION_CONFIRMED,
            Fortify::RECOVERY_CODES_GENERATED,
        ], true);

        return Inertia::render('settings/security', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'twoFactor' => [
                'enabled' => $enabled,
                'pending' => $pending,
            ],
            'setup' => $pending ? [
                'qrSvg' => $user->twoFactorQrCodeSvg(),
                'secret' => Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret),
            ] : null,
            'recoveryCodes' => $enabled && $justMade
                ? $user->recoveryCodes()
                : Inertia::optional(fn (): array => $enabled ? $user->recoveryCodes() : []),
        ]);
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }
}
