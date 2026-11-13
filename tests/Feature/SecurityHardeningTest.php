<?php

use App\Actions\Households\CreateHousehold;
use App\Models\Household;
use App\Models\User;

it('sends browser hardening headers', function () {
    $this->get('/')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'")
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeaderMissing('Strict-Transport-Security');
});

it('limits reset link requests from one visitor', function () {
    foreach (range(1, 5) as $attempt) {
        $this->post(route('password.email'), ['email' => "person{$attempt}@example.com"])->assertRedirect();
    }

    $this->post(route('password.email'), ['email' => 'person6@example.com'])->assertTooManyRequests();
});

it('only puts a known theme from the appearance cookie into the page', function () {
    $this->withUnencryptedCookie('appearance', "dark'</script><script>alert(1)//")
        ->get(route('login'))
        ->assertDontSee('alert(1)', false)
        ->assertSee("const appearance = 'light';", false);

    $user = User::factory()->create();
    $this->actingAs($user)->withUnencryptedCookie('appearance', 'nonsense')
        ->get(route('dashboard'))
        ->assertSee("const appearance = 'system';", false);
});

it('keeps the public pages light, whatever the theme setting', function () {
    $this->withUnencryptedCookie('appearance', 'dark')->get('/')
        ->assertSee('data-light-only="true"', false)
        ->assertDontSee('class="dark"', false);

    $this->actingAs(User::factory()->create())->withUnencryptedCookie('appearance', 'dark')
        ->get(route('dashboard'))
        ->assertSee('data-light-only="false"', false)
        ->assertSee('class="dark"', false);
});

it('only follows notification links that stay on this site', function (string $url, bool $follows) {
    $user = User::factory()->create();
    $notification = $user->notifications()->create([
        'id' => (string) str()->uuid(),
        'type' => 'test',
        'data' => ['url' => $url],
    ]);

    $response = $this->actingAs($user)->from('/notifications')->post(route('notifications.open', $notification->id));

    $response->assertRedirect($follows ? $url : '/notifications');
})->with([
    'a path' => ['/lists', true],
    'another host' => ['//evil.example', false],
    'a backslash host' => ['/\\evil.example', false],
    'a full URL' => ['https://evil.example', false],
]);

it('deletes a household whose name has a comma in it', function () {
    $owner = User::factory()->create();
    $household = app(CreateHousehold::class)->handle($owner, ['name' => 'Smith, Jones']);

    $this->actingAs($owner)->delete(route('households.destroy', $household), ['confirm_name' => 'Smith'])
        ->assertSessionHasErrors('confirm_name');
    $this->delete(route('households.destroy', $household), ['confirm_name' => 'Smith, Jones'])
        ->assertRedirect(route('households.index'));

    expect(Household::query()->whereKey($household->id)->exists())->toBeFalse();
});
