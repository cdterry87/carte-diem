<?php

use App\Enums\HouseholdRole;
use App\Models\AuditLog;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\User;
use App\Notifications\HouseholdInvitationNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function householdWithMember(HouseholdRole $role = HouseholdRole::Member): array
{
    $household = Household::factory()->create();
    $member = User::factory()->create();
    $household->addMember($member, $role);

    return [$household, $household->owner, $member];
}

it('creates a household with the creator as owner', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('households.store'), ['name' => 'Home'])->assertRedirect();

    expect($user->roleIn(Household::query()->sole()))->toBe(HouseholdRole::Owner);
    expect(AuditLog::query()->where('action', 'household.created')->exists())->toBeTrue();
});

it('hides households from non members', function () {
    $household = Household::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->get(route('households.show', $household))->assertNotFound();
    $this->patch(route('households.update', $household), ['name' => 'Mine'])->assertNotFound();
});

it('stops unverified users from household operations', function () {
    $this->actingAs(User::factory()->unverified()->create());

    $this->get(route('households.index'))->assertRedirect(route('verification.notice'));
    $this->post(route('households.store'), ['name' => 'Home'])->assertRedirect(route('verification.notice'));
    expect(Household::query()->count())->toBe(0);
});

it('lets admins invite and blocks plain members', function () {
    Notification::fake();
    [$household, , $member] = householdWithMember();
    $admin = User::factory()->create();
    $household->addMember($admin, HouseholdRole::Admin);

    $this->actingAs($member)->post(route('households.invitations.store', $household), ['email' => 'new@example.com'])->assertForbidden();

    $this->actingAs($admin)->post(route('households.invitations.store', $household), ['email' => 'New@Example.com'])->assertSessionHasNoErrors();
    $this->get(route('households.show', $household))->assertInertia(fn (Assert $page) => $page
        ->has('invitations', 1)
        ->missing('invitations.0.token_hash'));

    Notification::assertSentTo(new AnonymousNotifiable, HouseholdInvitationNotification::class);
    expect(HouseholdInvitation::query()->first()->email)->toBe('new@example.com');
});

it('stores only a hash of the invitation token', function () {
    Notification::fake();
    [$household, $owner] = householdWithMember();
    $this->actingAs($owner);

    $this->post(route('households.invitations.store', $household), ['email' => 'new@example.com'])->assertSessionHasNoErrors();

    Notification::assertSentTo(new AnonymousNotifiable, HouseholdInvitationNotification::class, function ($notification) {
        return HouseholdInvitation::query()->first()->token_hash === hash('sha256', $notification->token)
            && HouseholdInvitation::query()->first()->token_hash !== $notification->token;
    });
});

it('replaces an earlier pending invitation for the same email', function () {
    Notification::fake();
    [$household, $owner] = householdWithMember();
    $this->actingAs($owner);

    $this->post(route('households.invitations.store', $household), ['email' => 'new@example.com']);
    $this->post(route('households.invitations.store', $household), ['email' => 'new@example.com']);

    expect($household->invitations()->pending()->count())->toBe(1);
});

function invitationFor(Household $household, string $email, array $overrides = []): HouseholdInvitation
{
    return $household->invitations()->create([
        'email' => $email,
        'role' => HouseholdRole::Member,
        'token_hash' => hash('sha256', fake()->uuid()),
        'expires_at' => now()->addDay(),
        ...$overrides,
    ]);
}

it('accepts an invitation only for the invited verified email', function () {
    $household = Household::factory()->create();
    $invited = User::factory()->create(['email' => 'invited@example.com']);
    $invitation = invitationFor($household, 'invited@example.com');

    $this->actingAs(User::factory()->create())->post(route('invitations.household.accept', $invitation))->assertNotFound();

    $this->actingAs($invited)->post(route('invitations.household.accept', $invitation))->assertRedirect();

    expect($invited->roleIn($household))->toBe(HouseholdRole::Member);

    $this->post(route('invitations.household.accept', $invitation))->assertRedirect();
    expectConflictToast();
});

it('rejects expired, revoked, and declined invitations', function (array $overrides) {
    $household = Household::factory()->create();
    $invited = User::factory()->create(['email' => 'invited@example.com']);
    $invitation = invitationFor($household, 'invited@example.com', $overrides);

    $this->actingAs($invited)->post(route('invitations.household.accept', $invitation))->assertRedirect();
    expectConflictToast();

    expect($invited->roleIn($household))->toBeNull();
})->with([
    'expired' => [['expires_at' => now()->subMinute()]],
    'revoked' => [['revoked_at' => now()]],
    'declined' => [['declined_at' => now()]],
]);

it('declines an invitation without joining', function () {
    $household = Household::factory()->create();
    $invited = User::factory()->create(['email' => 'invited@example.com']);
    $invitation = invitationFor($household, 'invited@example.com');

    $this->actingAs($invited)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->has('householdInvitations', 1));
    $this->post(route('invitations.household.decline', $invitation))->assertRedirect();
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->has('householdInvitations', 0));

    expect($invited->roleIn($household))->toBeNull();
});

it('removes access immediately when a member leaves or is removed', function () {
    [$household, $owner, $member] = householdWithMember();

    $this->actingAs($member)->get(route('households.show', $household))->assertOk();
    $this->post(route('households.leave', $household))->assertRedirect();
    $this->get(route('households.show', $household))->assertNotFound();

    $household->addMember($member, HouseholdRole::Member);
    $this->actingAs($owner)->delete(route('households.members.destroy', [$household, $member]))->assertRedirect();

    expect($member->roleIn($household))->toBeNull();
});

it('keeps the owner until ownership is transferred', function () {
    [$household, $owner, $member] = householdWithMember();

    $this->actingAs($owner)->post(route('households.leave', $household))->assertRedirect();
    expectConflictToast();
    expect($owner->roleIn($household))->toBe(HouseholdRole::Owner);

    $this->post(route('households.transfer', $household), ['user' => $member->uuid])->assertSessionHasNoErrors();

    expect($member->roleIn($household))->toBe(HouseholdRole::Owner);
    expect($owner->roleIn($household))->toBe(HouseholdRole::Admin);
    expect($household->refresh()->owner_user_id)->toBe($member->id);

    $this->post(route('households.leave', $household))->assertRedirect();
    expect($owner->roleIn($household))->toBeNull();
});

it('only transfers to existing members', function () {
    [$household, $owner] = householdWithMember();
    $this->actingAs($owner)->post(route('households.transfer', $household), ['user' => User::factory()->create()->uuid])->assertNotFound();
});

it('restricts household deletion to the owner with name confirmation', function () {
    [$household, $owner] = householdWithMember();
    $admin = User::factory()->create();
    $household->addMember($admin, HouseholdRole::Admin);

    $this->actingAs($admin)->delete(route('households.destroy', $household), ['confirm_name' => $household->name])->assertForbidden();

    $this->actingAs($owner)->delete(route('households.destroy', $household), ['confirm_name' => 'nope'])->assertSessionHasErrors('confirm_name');
    $this->delete(route('households.destroy', $household), ['confirm_name' => $household->name])->assertRedirect();

    expect(Household::query()->find($household->id))->toBeNull();
});

it('blocks account deletion while owning a shared household', function () {
    [$household, $owner] = householdWithMember();
    $this->actingAs($owner)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect();
    expectConflictToast('Transfer ownership');
    expect(User::query()->find($owner->id))->not->toBeNull();
});

it('purges sole-member households with the account', function () {
    $household = Household::factory()->create();
    $this->actingAs($household->owner)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');

    expect(Household::withTrashed()->find($household->id))->toBeNull();
});

it('audits household invitation revocation and leaves accepted invitations alone', function () {
    $household = Household::factory()->create();
    $this->actingAs($household->owner);
    $pending = invitationFor($household, 'pending@example.com');
    $accepted = invitationFor($household, 'accepted@example.com', ['accepted_at' => now()]);

    $this->delete(route('households.invitations.destroy', [$household, $pending]))->assertRedirect();
    $this->delete(route('households.invitations.destroy', [$household, $accepted]))->assertRedirect();

    expect($pending->refresh()->revoked_at)->not->toBeNull()
        ->and($accepted->refresh()->revoked_at)->toBeNull()
        ->and(AuditLog::query()->where('action', 'household.invitation_revoked')->count())->toBe(1);
});

it('defaults a household to the creator time zone and lets managers change it', function () {
    $user = User::factory()->create(['timezone' => 'America/Chicago']);
    $this->actingAs($user)->post(route('households.store'), ['name' => 'Home'])->assertRedirect();
    $household = Household::query()->sole();
    expect($household->timezone)->toBe('America/Chicago');

    $this->patch(route('households.update', $household), ['timezone' => 'Asia/Tokyo'])->assertSessionHasNoErrors();
    expect($household->refresh()->timezone)->toBe('Asia/Tokyo');
    $this->patch(route('households.update', $household), ['timezone' => null])->assertSessionHasErrors('timezone');
    $this->patch(route('households.update', $household), ['timezone' => 'Mars/Olympus'])->assertSessionHasErrors('timezone');

    $member = User::factory()->create();
    $household->addMember($member, HouseholdRole::Member);
    $this->actingAs($member)->patch(route('households.update', $household), ['timezone' => 'UTC'])->assertForbidden();
});
