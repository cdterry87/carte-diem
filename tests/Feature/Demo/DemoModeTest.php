<?php

use App\Models\Household;
use App\Models\ShoppingList;
use App\Models\User;
use App\Support\DemoAccount;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['demo.email' => 'demo@example.com', 'demo.password' => 'password', 'demo.timezone' => 'America/Chicago']);
});

function demoUser(): User
{
    return app(DemoAccount::class)->reset();
}

describe('with demo mode on', function () {
    beforeEach(fn () => config(['demo.mode' => true]));

    it('closes sign-up, including a form left open from before', function () {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'password', 'password_confirmation' => 'password'])->assertNotFound();

        expect(User::query()->count())->toBe(0);
        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('canRegister', false));
    });

    it('offers the demo login without showing the credentials', function () {
        demoUser();

        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
            ->component('auth/login')
            ->where('demoLogin', true))
            ->assertDontSee('demo@example.com');
    });

    it('hides the demo login until the credentials are set and the account exists', function () {
        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('demoLogin', false));

        demoUser();
        config(['demo.password' => null]);
        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('demoLogin', false));
        $this->post(route('demo.login'))->assertNotFound();
    });

    it('logs in as the demo user with one click', function () {
        $demo = demoUser();

        $this->post(route('demo.login'))->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($demo);
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('demo.isDemoUser', true));
    });

    it('keeps visitors from locking each other out of the demo account', function () {
        $demo = demoUser();
        $this->actingAs($demo)->withSession(['auth.password_confirmed_at' => time()]);

        $this->put(route('user-password.update'), ['current_password' => 'password', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])->assertRedirect();
        expectConflictToast('demo account');
        $this->post(route('two-factor.enable'))->assertRedirect();
        $this->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect();
        $this->patch(route('profile.update'), ['name' => 'Renamed', 'email' => 'mine@example.com'])->assertRedirect();

        $demo->refresh();
        expect($demo->email)->toBe('demo@example.com')
            ->and($demo->two_factor_secret)->toBeNull()
            ->and(Hash::check('password', $demo->password))->toBeTrue();

        $this->patch(route('profile.update'), ['name' => 'Renamed', 'email' => 'demo@example.com'])->assertSessionHasNoErrors();
        expect($demo->refresh()->name)->toBe('Renamed');
    });

    it('sends no invitations from the demo account', function () {
        Notification::fake();
        $demo = demoUser();
        $household = Household::query()->sole();
        $list = ShoppingList::query()->where('owner_type', 'household')->firstOrFail();

        $this->actingAs($demo)->post(route('households.invitations.store', $household), ['email' => 'stranger@example.com'])->assertRedirect();
        $this->post(route('lists.shares.store', $list), ['email' => 'stranger@example.com'])->assertRedirect();

        Notification::assertNothingSent();
        expect($household->invitations()->count())->toBe(0);
    });

    it('turns live updates off, so the site needs no WebSocket server', function () {
        config(['broadcasting.default' => 'reverb']);

        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('liveUpdates', false));
    });
});

it('broadcasts nothing in demo mode, whatever BROADCAST_CONNECTION says', function (string $demoMode, string $broadcaster) {
    $previous = [$_ENV['DEMO_MODE'] ?? null, $_ENV['BROADCAST_CONNECTION'] ?? null];
    $_ENV['DEMO_MODE'] = $demoMode;
    $_ENV['BROADCAST_CONNECTION'] = 'reverb';

    try {
        expect((require config_path('broadcasting.php'))['default'])->toBe($broadcaster);
    } finally {
        [$_ENV['DEMO_MODE'], $_ENV['BROADCAST_CONNECTION']] = $previous;
    }
})->with([
    'demo mode on' => ['true', 'null'],
    'demo mode off' => ['false', 'reverb'],
]);

describe('with demo mode off', function () {
    beforeEach(fn () => config(['demo.mode' => false]));

    it('allows sign-up and shows no demo login', function () {
        demoUser();

        $this->get('/register')->assertOk();
        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
            ->where('demoLogin', false)
            ->where('canRegister', true));
        $this->post(route('demo.login'))->assertNotFound();
        $this->assertGuest();
    });

    it('keeps live updates on when Reverb is configured', function () {
        config(['broadcasting.default' => 'reverb']);

        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('liveUpdates', true));
    });
});

it('never sends the demo account a password reset link', function () {
    Notification::fake();
    $demo = demoUser();
    $regular = User::factory()->create();

    $this->post(route('password.email'), ['email' => 'Demo@Example.com'])->assertSessionHas('status');
    $this->post(route('password.email'), ['email' => $regular->email]);

    Notification::assertNotSentTo($demo, ResetPassword::class);
    Notification::assertSentTo($regular, ResetPassword::class);
});
