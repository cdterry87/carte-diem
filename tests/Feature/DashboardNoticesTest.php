<?php

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function dashboardNotice($test, User $user): ?string
{
    $notice = null;
    $test->actingAs($user)->get(route('dashboard'))->assertOk()
        ->assertInertia(function (Assert $page) use (&$notice) {
            $notice = $page->toArray()['props']['notice'];
        });

    return $notice;
}

function joinHousehold(User $user): void
{
    Household::factory()->create()->members()->attach($user, ['role' => HouseholdRole::Owner, 'joined_at' => now()]);
}

it('invites someone outside a household to create one, until they dismiss it', function () {
    $user = User::factory()->create();
    expect(dashboardNotice($this, $user))->toBe('household');

    $this->post(route('notices.dismiss', 'household'))->assertRedirect();
    expect(dashboardNotice($this, $user->fresh()))->toBeNull()
        ->and($user->fresh()->dismissed_notices)->toBe(['household']);

});

it('hides the household tip once someone belongs to a household', function () {
    $user = User::factory()->create();
    joinHousehold($user);
    expect(dashboardNotice($this, $user))->toBeNull();

    $this->post(route('notices.dismiss', 'household'))->assertRedirect();
    $this->post(route('notices.dismiss', 'household'))->assertRedirect();
    expect($user->fresh()->dismissed_notices)->toBe(['household']);
});

it('rejects unknown notices and guests', function () {
    $this->post('/dashboard/notices/household/dismiss')->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->post('/dashboard/notices/nonsense/dismiss')->assertNotFound();
    $this->post('/dashboard/notices/food_needs/dismiss')->assertNotFound();
});
