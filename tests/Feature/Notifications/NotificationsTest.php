<?php

use App\Actions\Notifications\SendListActivityDigests;
use App\Actions\Notifications\SendStapleReminders;
use App\Enums\HouseholdRole;
use App\Enums\ListShareRole;
use App\Enums\NotificationType;
use App\Models\Household;
use App\Models\ListActivityEvent;
use App\Models\NotificationPreference;
use App\Models\RecommendationDismissal;
use App\Models\ShoppingList;
use App\Models\ShoppingTrip;
use App\Models\StapleRule;
use App\Models\User;
use App\Notifications\InvitationReceived;
use App\Notifications\ListActivityDigest;
use App\Notifications\StapleRemindersDue;
use App\Notifications\TripCompletedNotification;
use App\Services\NotificationChannels;
use App\Services\ProductResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function staple(Model $owner, string $name, int $cadence = 7, int $createdDaysAgo = 30): StapleRule
{
    $rule = StapleRule::query()->create([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
        'product_id' => app(ProductResolver::class)->resolve($owner, $name)->id,
        'cadence_days' => $cadence,
    ]);
    $rule->forceFill(['created_at' => now()->subDays($createdDaysAgo)])->save();

    return $rule;
}

function activityList(Model $owner): ShoppingList
{
    return ShoppingList::query()->create(['owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey(), 'title' => 'Weekly']);
}

it('batches due staples into one reminder, worded as a possibility', function () {
    Notification::fake();
    $user = User::factory()->create();
    staple($user, 'Coffee');
    staple($user, 'Tea');
    staple($user, 'Not yet', 90, 1);

    expect(app(SendStapleReminders::class)->handle())->toBe(1);

    Notification::assertSentTo($user, StapleRemindersDue::class, function (StapleRemindersDue $notification) use ($user) {
        $data = $notification->toArray($user);

        return $notification->names === ['Coffee', 'Tea']
            && str_contains($data['body'], 'may need')
            && ! str_contains(strtolower($data['body']), 'out of');
    });
});

it('sends one reminder per staple per window', function () {
    Notification::fake();
    $user = User::factory()->create();
    staple($user, 'Coffee');

    expect(app(SendStapleReminders::class)->handle())->toBe(1)
        ->and(app(SendStapleReminders::class)->handle())->toBe(0);

    $this->travel(8)->days();
    expect(app(SendStapleReminders::class)->handle())->toBe(1);
});

it('skips disabled, snoozed, muted, and already listed staples', function () {
    Notification::fake();
    $user = User::factory()->create();
    staple($user, 'Off')->update(['reminder_enabled' => false]);
    staple($user, 'Snoozed')->update(['snoozed_until' => now()->addDay()]);
    $muted = staple($user, 'Muted');
    RecommendationDismissal::query()->create(['user_id' => $user->id, 'product_id' => $muted->product_id, 'reason' => 'mute']);
    $listed = staple($user, 'Listed');
    activityList($user)->items()->create(['name_snapshot' => 'Listed', 'product_id' => $listed->product_id]);

    expect(app(SendStapleReminders::class)->handle())->toBe(0);
    Notification::assertNothingSent();
});

it('reminds every household member and tracks them separately', function () {
    Notification::fake();
    $household = Household::factory()->create();
    $member = User::factory()->create();
    $household->addMember($member, HouseholdRole::Member);
    staple($household, 'Coffee');

    expect(app(SendStapleReminders::class)->handle())->toBe(2);
    Notification::assertSentTo([$household->owner, $member], StapleRemindersDue::class);
});

it('holds reminders during quiet hours and sends them afterwards', function () {
    Notification::fake();
    $this->travelTo(now()->setTimezone('UTC')->setTime(3, 0));
    $user = User::factory()->create(['timezone' => 'UTC', 'quiet_start' => '22:00:00', 'quiet_end' => '07:00:00']);
    staple($user, 'Coffee');

    expect(app(SendStapleReminders::class)->handle())->toBe(0);

    $this->travelTo(now()->setTime(8, 0));
    expect(app(SendStapleReminders::class)->handle())->toBe(1);
});

it('evaluates quiet hours in the user time zone, across midnight or within a day', function () {
    $channels = app(NotificationChannels::class);
    $this->travelTo(now()->setTimezone('UTC')->setTime(4, 0));

    expect($channels->isQuiet(User::factory()->make(['timezone' => 'UTC', 'quiet_start' => '22:00:00', 'quiet_end' => '07:00:00'])))->toBeTrue()
        ->and($channels->isQuiet(User::factory()->make(['timezone' => 'America/Chicago', 'quiet_start' => '22:00:00', 'quiet_end' => '23:30:00'])))->toBeTrue()
        ->and($channels->isQuiet(User::factory()->make(['timezone' => 'UTC', 'quiet_start' => '09:00:00', 'quiet_end' => '17:00:00'])))->toBeFalse()
        ->and($channels->isQuiet(User::factory()->make(['timezone' => 'UTC'])))->toBeFalse();
});

it('routes channels by preference and quiet hours', function () {
    $channels = app(NotificationChannels::class);
    $user = User::factory()->create();

    expect($channels->for($user, NotificationType::ReminderDue))->toBe(['database', 'mail'])
        ->and($channels->for($user, NotificationType::ListActivity))->toBe(['database']);

    NotificationPreference::query()->create(['user_id' => $user->id, 'type' => NotificationType::ReminderDue, 'in_app' => false, 'email' => true]);
    expect($channels->for($user, NotificationType::ReminderDue))->toBe(['mail']);

    $this->travelTo(now()->setTimezone('UTC')->setTime(3, 0));
    $user->update(['timezone' => 'UTC', 'quiet_start' => '22:00', 'quiet_end' => '07:00']);
    expect($channels->for($user->refresh(), NotificationType::ReminderDue))->toBe([]);

    expect($channels->for(User::factory()->unverified()->create(), NotificationType::ReminderDue))->toBe(['database']);
});

it('batches list activity to others, with counts and no item names', function () {
    Notification::fake();
    $household = Household::factory()->create();
    $member = User::factory()->create(['name' => 'Alex']);
    $household->addMember($member, HouseholdRole::Member);
    $list = activityList($household);
    $guest = User::factory()->create();
    $list->shares()->create(['user_id' => $guest->id, 'role' => ListShareRole::Viewer]);

    $this->actingAs($member);
    $this->post(route('lists.items.store', $list), ['name' => 'secret medicine']);
    $this->post(route('lists.items.store', $list), ['name' => 'milk']);
    $item = $list->items()->where('name_snapshot', 'milk')->firstOrFail();
    $this->delete(route('lists.items.destroy', [$list, $item->uuid]));

    expect(app(SendListActivityDigests::class)->handle())->toBe(0);

    $this->travel(6)->minutes();

    expect(app(SendListActivityDigests::class)->handle())->toBe(2)
        ->and(app(SendListActivityDigests::class)->handle())->toBe(0);

    Notification::assertNotSentTo($member, ListActivityDigest::class);
    Notification::assertSentTo($household->owner, ListActivityDigest::class, fn (ListActivityDigest $digest) => $digest->summary === 'Alex added 2 items and removed 1 item.'
        && ! str_contains($digest->summary, 'medicine'));
    Notification::assertSentTo($guest, ListActivityDigest::class);
});

it('stops digests to people who lost access', function () {
    Notification::fake();
    $household = Household::factory()->create();
    $leaver = User::factory()->create();
    $household->addMember($leaver, HouseholdRole::Member);
    $list = activityList($household);
    $this->actingAs($household->owner)->post(route('lists.items.store', $list), ['name' => 'milk']);
    $household->members()->detach($leaver->id);

    $this->travel(6)->minutes();
    app(SendListActivityDigests::class)->handle();

    Notification::assertNotSentTo($leaver, ListActivityDigest::class);
    expect(ListActivityEvent::query()->whereNull('processed_at')->count())->toBe(0);
});

it('notifies others when a trip completes, without purchase details', function () {
    Notification::fake();
    $household = Household::factory()->create();
    $member = User::factory()->create(['name' => 'Alex']);
    $household->addMember($member, HouseholdRole::Member);
    $list = activityList($household);
    $list->items()->create(['name_snapshot' => 'secret medicine']);

    $this->actingAs($member)->post(route('trips.store', $list))->assertRedirect();
    $trip = ShoppingTrip::query()->sole();
    $this->patch(route('trips.items.update', [$trip, $trip->items()->sole()]), ['purchased' => true])->assertRedirect();
    $this->post(route('trips.complete', $trip))->assertRedirect();

    Notification::assertNotSentTo($member, TripCompletedNotification::class);
    Notification::assertSentTo($household->owner, TripCompletedNotification::class, fn (TripCompletedNotification $notification) => $notification->summary === 'Alex finished a trip and bought 1 item.');

    $this->post(route('trips.complete', $trip))->assertRedirect();
    Notification::assertSentTimes(TripCompletedNotification::class, 1);
});

it('adds an in-app notice when the invited person already has an account', function () {
    Notification::fake();
    $household = Household::factory()->create();
    $existing = User::factory()->create(['email' => 'here@example.com']);
    $this->actingAs($household->owner);

    $this->post(route('households.invitations.store', $household), ['email' => 'here@example.com'])->assertSessionHasNoErrors();
    $this->post(route('households.invitations.store', $household), ['email' => 'new@example.com'])->assertSessionHasNoErrors();

    Notification::assertSentTo($existing, InvitationReceived::class);
    Notification::assertSentTimes(InvitationReceived::class, 1);
});

it('shows only the user their notifications, and opens and clears them', function () {
    $user = User::factory()->create();
    $user->notify(new TripCompletedNotification('Weekly', 'Alex finished.', '/lists'));
    $user->notify(new TripCompletedNotification('Weekly', 'Sam finished.', '/lists'));
    $other = User::factory()->create();
    $other->notify(new TripCompletedNotification('Hidden', 'Hidden.', '/lists'));
    $foreignId = $other->notifications()->first()->id;

    $this->actingAs($user)->get(route('notifications.index'))->assertInertia(fn (Assert $page) => $page
        ->component('notifications/index')
        ->has('notifications', 2)
        ->where('unreadNotifications', 2));

    $this->post(route('notifications.open', $foreignId))->assertNotFound();
    $this->post(route('notifications.open', $user->notifications()->first()->id))->assertRedirect('/lists');
    expect($user->unreadNotifications()->count())->toBe(1);

    $this->post(route('notifications.read-all'))->assertRedirect();
    expect($user->unreadNotifications()->count())->toBe(0)
        ->and($other->unreadNotifications()->count())->toBe(1);

    $this->actingAs($other)->get(route('notifications.index'))->assertInertia(fn (Assert $page) => $page
        ->has('notifications', 1)
        ->where('notifications.0.title', 'Trip finished: Hidden'));
    $this->post(route('notifications.open', $user->notifications()->first()->id))->assertNotFound();
});

it('saves preferences and quiet hours, keeping invitation email on', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->put(route('notification-settings.update'), [
        'preferences' => [
            ['type' => 'reminder_due', 'in_app' => false, 'email' => false],
            ['type' => 'invitation', 'in_app' => false, 'email' => false],
        ],
        'quiet_start' => '22:00',
        'quiet_end' => '07:00',
    ])->assertSessionHasNoErrors();
    expect($user->fresh()->quiet_start)->toStartWith('22:00');

    $this->put(route('notification-settings.update'), ['quiet_start' => '22:00', 'quiet_end' => null])->assertSessionHasErrors('quiet_end');
    $this->put(route('notification-settings.update'), ['preferences' => [['type' => 'bogus', 'in_app' => true, 'email' => true]]])->assertSessionHasErrors();

    $channels = app(NotificationChannels::class);
    expect($channels->preference($user, NotificationType::ReminderDue))->toBe(['in_app' => false, 'email' => false])
        ->and(NotificationPreference::query()->where('type', 'invitation')->sole()->email)->toBeFalse();

    $this->put(route('notification-settings.update'), ['quiet_start' => null, 'quiet_end' => null])->assertSessionHasNoErrors();
    expect($user->fresh()->quiet_start)->toBeNull();

    $this->get(route('notification-settings.edit'))->assertInertia(fn (Assert $page) => $page
        ->component('settings/notifications')
        ->has('settings.preferences', 4));
});

it('removes notifications and preferences with the account', function () {
    $user = User::factory()->create();
    $user->notify(new TripCompletedNotification('Weekly', 'x', '/lists'));
    $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');

    expect(DatabaseNotification::query()->count())->toBe(0);
});

it('runs both scheduled commands', function () {
    $this->artisan('reminders:send')->assertSuccessful();
    $this->artisan('notifications:send-digests')->assertSuccessful();
});

it('renders staples and suggestion props on web pages', function () {
    $user = User::factory()->create();
    staple($user, 'Coffee');
    $list = activityList($user);
    $this->actingAs($user);

    $this->get(route('staples.index'))->assertInertia(fn (Assert $page) => $page->component('staples/index')->has('staples', 1)->where('muted', []));
    $this->get(route('lists.show', $list))->assertInertia(fn (Assert $page) => $page->has('suggestions', 1)->where('suggestions.0.name', 'Coffee'));
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->has('suggestions', 1));
});
