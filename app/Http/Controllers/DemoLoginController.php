<?php

namespace App\Http\Controllers;

use App\Support\DemoAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DemoLoginController extends Controller
{
    /**
     * Log in as the shared demo account with one click. The credentials never reach the page.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(DemoAccount::loginAvailable(), 404);

        $user = DemoAccount::user();
        abort_if($user === null, 404);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(config('fortify.home'));
    }
}
