<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

/**
 * A valid code for the user. Fortify refuses a code it has already accepted, so a later step can
 * ask for the next period's code, which is still inside the allowed window.
 */
function otpFor(User $user, int $periodsAhead = 0): string
{
    $google2fa = app(Google2FA::class);
    $secret = Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret);

    return $google2fa->oathTotp($secret, $google2fa->getTimestamp() + $periodsAhead);
}

function confirmedPassword($test, User $user)
{
    return $test->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
}

function withTwoFactor($test, User $user): User
{
    confirmedPassword($test, $user)->post(route('two-factor.enable'))->assertRedirect();
    $user->refresh();
    $test->post(route('two-factor.confirm'), ['code' => otpFor($user)])->assertRedirect();
    // The redirect back to the page uses up the one-time status, as a browser would.
    $test->get(route('security.edit'))->assertOk();
    auth()->logout();

    return $user->refresh();
}

it('is off by default, and signing in needs only a password', function () {
    $user = User::factory()->create();

    confirmedPassword($this, $user)->get(route('security.edit'))->assertInertia(fn (Assert $page) => $page
        ->where('twoFactor', ['enabled' => false, 'pending' => false])
        ->where('setup', null));

    auth()->logout();
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

it('sets up with a QR code, and turns on only after a valid code', function () {
    $user = User::factory()->create();

    confirmedPassword($this, $user)->post(route('two-factor.enable'))->assertRedirect();
    $this->get(route('security.edit'))->assertInertia(fn (Assert $page) => $page
        ->where('twoFactor', ['enabled' => false, 'pending' => true])
        ->where('setup.qrSvg', fn (string $svg) => str_contains($svg, '<svg'))
        ->has('setup.secret')
        ->missing('recoveryCodes'));

    $this->post(route('two-factor.confirm'), ['code' => '000000'])
        ->assertSessionHasErrors('code', errorBag: 'confirmTwoFactorAuthentication');
    expect($user->fresh()->hasEnabledTwoFactorAuthentication())->toBeFalse();

    $this->post(route('two-factor.confirm'), ['code' => otpFor($user->fresh())])->assertRedirect();
    expect($user->fresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();

    $this->get(route('security.edit'))->assertInertia(fn (Assert $page) => $page
        ->where('twoFactor', ['enabled' => true, 'pending' => false])
        ->where('setup', null)
        ->has('recoveryCodes', 8));
});

it('sends recovery codes only when asked for after setup', function () {
    $user = withTwoFactor($this, User::factory()->create());

    confirmedPassword($this, $user)->get(route('security.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('recoveryCodes')
            ->reloadOnly('recoveryCodes', fn (Assert $reload) => $reload->has('recoveryCodes', 8)));
});

it('asks for a code at sign-in once it is on, and accepts a recovery code once', function () {
    $user = withTwoFactor($this, User::factory()->create());

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
    $this->get(route('two-factor.login'))->assertInertia(fn (Assert $page) => $page->component('auth/two-factor-challenge'));

    $this->post(route('two-factor.login.store'), ['code' => '000000'])->assertRedirect(route('two-factor.login'));
    $this->assertGuest();

    $this->post(route('two-factor.login.store'), ['code' => otpFor($user, 1)])->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);

    auth()->logout();
    $recovery = $user->recoveryCodes()[0];
    foreach ([true, false] as $works) {
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
        $this->post(route('two-factor.login.store'), ['recovery_code' => $recovery]);
        $works ? $this->assertAuthenticatedAs($user) : $this->assertGuest();
        auth()->logout();
    }
});

it('turns off again, and then signing in needs only a password', function () {
    $user = withTwoFactor($this, User::factory()->create());

    confirmedPassword($this, $user)->delete(route('two-factor.disable'))->assertRedirect();
    expect($user->fresh()->two_factor_secret)->toBeNull();

    auth()->logout();
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard'));
});

it('needs a recent password to change two-factor settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('two-factor.enable'))->assertRedirect(route('password.confirm'));
    expect($user->fresh()->two_factor_secret)->toBeNull();
});
