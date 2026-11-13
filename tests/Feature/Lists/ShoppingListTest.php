<?php

use App\Enums\HouseholdRole;
use App\Enums\ListShareRole;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Household;
use App\Models\ListShareInvitation;
use App\Models\PurchaseTransaction;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\Store;
use App\Models\User;
use App\Notifications\ListShareInvitationNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

function createList(Model $owner, ?User $creator = null, array $attributes = []): ShoppingList
{
    return ShoppingList::query()->create([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
        'title' => 'Groceries',
        'created_by' => $creator?->id,
        ...$attributes,
    ]);
}

/**
 * @return array<string, mixed> The list as its page shows it.
 */
function listPage($test, ShoppingList|string $list): array
{
    return pageProps($test->get(route('lists.show', $list)), 'list');
}

/**
 * @return array<int, array<string, mixed>> The list's items as its page shows them.
 */
function listItems($test, ShoppingList $list): array
{
    return pageProps($test->get(route('lists.show', $list)), 'items');
}

/**
 * @return array<int, array<string, mixed>>
 */
function listsPage($test, array $query = []): array
{
    return pageProps($test->get(route('lists.index', $query)), 'lists');
}

/**
 * Quick-add an item through the web, and return it as the list page shows it.
 *
 * @return array<string, mixed>
 */
function addItem($test, ShoppingList $list, array $data): array
{
    $test->post(route('lists.items.store', $list), $data)->assertSessionHasNoErrors()->assertRedirect();
    $model = ShoppingListItem::query()->where('shopping_list_id', $list->id)->latest('updated_at')->latest('id')->firstOrFail();

    return collect(listItems($test, $list))->firstWhere('uuid', $model->uuid);
}

function shareList(ShoppingList $list, ListShareRole $role): User
{
    $guest = User::factory()->create();
    $list->shares()->create(['user_id' => $guest->id, 'role' => $role]);

    return $guest;
}

it('creates personal and household lists', function () {
    $household = Household::factory()->create();
    $this->actingAs($household->owner);

    $this->post(route('lists.store'), ['owner' => ['type' => 'user'], 'title' => 'Mine'])->assertRedirect();
    expect(listPage($this, ShoppingList::query()->where('title', 'Mine')->sole())['owner']['type'])->toBe('user');

    $this->post(route('lists.store'), ['owner' => ['type' => 'household', 'uuid' => $household->uuid], 'title' => 'Ours'])->assertRedirect();
    expect(listPage($this, ShoppingList::query()->where('title', 'Ours')->sole())['owner']['name'])->toBe($household->name);
});

it('rejects lists for a household the user is not in', function () {
    $household = Household::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->post(route('lists.store'), ['owner' => ['type' => 'household', 'uuid' => $household->uuid], 'title' => 'X'])->assertNotFound();
});

it('rejects a default store from another scope', function () {
    $user = User::factory()->create();
    $otherStore = Store::query()->create(['scope_type' => 'user', 'scope_id' => User::factory()->create()->id, 'name' => 'Theirs']);
    $this->actingAs($user);

    $this->post(route('lists.store'), ['owner' => ['type' => 'user'], 'title' => 'X', 'default_store' => $otherStore->uuid])
        ->assertSessionHasErrors('default_store');
});

it('shows each user only accessible lists', function () {
    $user = User::factory()->create();
    $household = Household::factory()->create();
    $household->addMember($user, HouseholdRole::Member);

    $personal = createList($user);
    $shared = createList(User::factory()->create(), attributes: ['title' => 'Shared']);
    $shared->shares()->create(['user_id' => $user->id, 'role' => 'viewer']);
    $householdList = createList($household);
    $hidden = createList(User::factory()->create());
    createList(Household::factory()->create());

    $this->actingAs($user);

    $uuids = collect(listsPage($this))->pluck('uuid');

    expect($uuids)->toHaveCount(3)->toContain($personal->uuid, $shared->uuid, $householdList->uuid)->not->toContain($hidden->uuid);

    expect(listsPage($this, ['scope' => 'personal']))->toHaveCount(1);
    expect(listsPage($this, ['scope' => 'shared']))->toHaveCount(1);
    expect(listsPage($this, ['scope' => $household->uuid]))->toHaveCount(1);
});

it('rejects cross-scope access with 404', function () {
    $list = createList(User::factory()->create());
    $this->actingAs(User::factory()->create());

    $this->get(route('lists.show', $list))->assertNotFound();
    $this->getJson(route('lists.suggestions', $list))->assertNotFound();
    $this->post(route('lists.items.store', $list), ['name' => 'x'])->assertNotFound();
    $this->patch(route('lists.update', $list), ['title' => 'x'])->assertNotFound();
    $this->delete(route('lists.destroy', $list))->assertNotFound();
});

it('ends household access when membership ends', function () {
    $household = Household::factory()->create();
    $member = User::factory()->create();
    $household->addMember($member, HouseholdRole::Member);
    $list = createList($household);

    $this->actingAs($member)->get(route('lists.show', $list))->assertOk();

    $household->members()->detach($member->id);
    $this->get(route('lists.show', $list))->assertNotFound();
});

it('lets any member edit and archive, but only creator or manager delete', function () {
    $household = Household::factory()->create();
    $creator = User::factory()->create();
    $member = User::factory()->create();
    $admin = User::factory()->create();
    $household->addMember($creator, HouseholdRole::Member);
    $household->addMember($member, HouseholdRole::Member);
    $household->addMember($admin, HouseholdRole::Admin);
    $list = createList($household, $creator);

    $this->actingAs($member);
    $this->patch(route('lists.update', $list), ['title' => 'Renamed'])->assertSessionHasNoErrors();
    $this->post(route('lists.archive', $list))->assertRedirect();
    expect($list->refresh()->status->value)->toBe('archived');
    $this->post(route('lists.unarchive', $list))->assertRedirect();
    $this->delete(route('lists.destroy', $list))->assertForbidden();
    $this->post(route('lists.shares.store', $list), ['email' => 'a@example.com'])->assertForbidden();

    $this->actingAs($creator);
    $this->delete(route('lists.destroy', $list))->assertRedirect();
    $this->get(route('lists.show', $list))->assertNotFound();
    $this->post(route('lists.restore', $list))->assertRedirect();

    $this->actingAs($admin)->delete(route('lists.destroy', $list))->assertRedirect();

    $this->actingAs($member)->post(route('lists.restore', $list))->assertForbidden();
});

it('keeps favorites per user', function () {
    $household = Household::factory()->create();
    $member = User::factory()->create();
    $household->addMember($member, HouseholdRole::Member);
    $list = createList($household);

    $this->actingAs($member)->post(route('lists.favorite', $list))->assertRedirect();
    expect(listPage($this, $list)['is_favorite'])->toBeTrue();

    $this->actingAs($household->owner);
    expect(listPage($this, $list)['is_favorite'])->toBeFalse();
});

it('quick adds with parsing, inference, and explicit overrides', function () {
    $user = User::factory()->create();
    $list = createList($user);
    $this->actingAs($user);

    expect(addItem($this, $list, ['name' => '2 lb apples']))
        ->name->toBe('apples')
        ->quantity->toBe('2.000')
        ->unit->toBe('lb')
        ->category->slug->toBe('produce')
        ->status->toBe('needed');

    expect(addItem($this, $list, ['name' => 'Unknown widget']))
        ->category->slug->toBe('other')
        ->quantity->toBeNull();

    expect(addItem($this, $list, ['name' => '2 lb apples', 'quantity' => 5]))
        ->name->toBe('2 lb apples')
        ->quantity->toBe('5.000');
});

it('rejects categories from another scope on items', function () {
    $user = User::factory()->create();
    $list = createList($user);
    $stranger = User::factory()->create();
    $this->actingAs($stranger)->post(route('organize.categories.store'), ['owner' => ['type' => 'user'], 'name' => 'Foreign']);
    $foreign = Category::query()->where('scope_id', $stranger->id)->sole()->uuid;

    $this->actingAs($user)->post(route('lists.items.store', $list), ['name' => 'x', 'category' => $foreign])->assertSessionHasErrors('category');
});

it('checks off directly without writing purchases', function () {
    $user = User::factory()->create();
    $list = createList($user);
    $this->actingAs($user);

    $first = addItem($this, $list, ['name' => 'milk'])['uuid'];
    addItem($this, $list, ['name' => 'eggs']);

    $this->patch(route('lists.items.update', [$list, $first]), ['status' => 'satisfied'])->assertSessionHasNoErrors();

    $items = collect(listItems($this, $list))->keyBy('name');
    expect($items['milk'])->status->toBe('satisfied')->revision->toBe(2)
        ->and($items['eggs']['status'])->toBe('needed')
        ->and(PurchaseTransaction::query()->count())->toBe(0);
});

it('refuses a stale revision without overwriting', function () {
    $user = User::factory()->create();
    $list = createList($user);
    $this->actingAs($user);

    $uuid = addItem($this, $list, ['name' => 'milk'])['uuid'];
    $this->patch(route('lists.items.update', [$list, $uuid]), ['notes' => 'a', 'revision' => 1])->assertSessionHasNoErrors();

    $this->patch(route('lists.items.update', [$list, $uuid]), ['notes' => 'b', 'revision' => 1])->assertRedirect();
    expectConflictToast();

    expect($list->items()->first()->notes)->toBe('a');
});

it('removes and restores items, scoped to their list', function () {
    $user = User::factory()->create();
    $list = createList($user);
    $other = createList($user);
    $this->actingAs($user);

    $uuid = addItem($this, $list, ['name' => 'milk'])['uuid'];

    $this->delete(route('lists.items.destroy', [$other, $uuid]))->assertNotFound();
    $this->delete(route('lists.items.destroy', [$list, $uuid]))->assertRedirect();
    expect(listItems($this, $list))->toHaveCount(0);
    $this->post(route('lists.items.restore', [$list, $uuid]))->assertRedirect();
    expect(listItems($this, $list))->toHaveCount(1);
});

it('reorders items only within the list', function () {
    $user = User::factory()->create();
    $list = createList($user);
    $other = createList($user);
    $this->actingAs($user);

    $a = addItem($this, $list, ['name' => 'a'])['uuid'];
    $b = addItem($this, $list, ['name' => 'b'])['uuid'];
    $foreign = addItem($this, $other, ['name' => 'c'])['uuid'];

    $this->post(route('lists.items.reorder', $list), ['items' => [$b, $a, $foreign]])->assertRedirect();

    expect(listItems($this, $list)[0]['uuid'])->toBe($b);
    expect($other->items()->first()->sort_order)->toBe(1);
});

it('duplicates a list with every item needed again', function () {
    $user = User::factory()->create();
    $list = createList($user);
    $this->actingAs($user);

    $uuid = addItem($this, $list, ['name' => 'milk'])['uuid'];
    $this->patch(route('lists.items.update', [$list, $uuid]), ['status' => 'satisfied']);

    $this->post(route('lists.duplicate', $list))->assertRedirect();
    $copy = ShoppingList::query()->latest('id')->firstOrFail();

    expect($copy->title)->toBe('Groceries (copy)')
        ->and(listItems($this, $copy)[0]['status'])->toBe('needed')
        ->and(listItems($this, $list)[0]['status'])->toBe('satisfied');
});

it('restricts guests by role', function () {
    $list = createList(User::factory()->create());
    $editor = shareList($list, ListShareRole::Editor);
    $viewer = shareList($list, ListShareRole::Viewer);

    $this->actingAs($viewer);
    expect(listPage($this, $list)['can']['edit_items'])->toBeFalse();
    $this->post(route('lists.items.store', $list), ['name' => 'x'])->assertForbidden();
    $this->getJson(route('lists.suggestions', $list))->assertForbidden();

    $this->actingAs($editor);
    $this->post(route('lists.items.store', $list), ['name' => 'x'])->assertRedirect();
    $this->patch(route('lists.update', $list), ['title' => 'x'])->assertForbidden();
    $this->post(route('lists.archive', $list))->assertForbidden();
    $this->delete(route('lists.destroy', $list))->assertForbidden();
    $this->post(route('lists.duplicate', $list))->assertForbidden();
    expect(pageProps($this->get(route('lists.show', $list)), 'shares'))->toBe([]);
});

it('never grants household access through a list share', function () {
    $household = Household::factory()->create();
    $list = createList($household);
    $hidden = createList($household, attributes: ['title' => 'Hidden']);
    $guest = shareList($list, ListShareRole::Editor);

    $this->actingAs($guest);
    $this->get(route('households.show', $household))->assertNotFound();
    $this->get(route('lists.show', $hidden))->assertNotFound();
    $this->get(route('organize.index', ['household' => $household->uuid]))->assertNotFound();
    expect($guest->belongsToScope($household))->toBeFalse();
});

it('limits guest suggestions to the list and their own products', function () {
    $household = Household::factory()->create();
    $list = createList($household);
    $guest = shareList($list, ListShareRole::Editor);

    $this->actingAs($household->owner);
    addItem($this, $list, ['name' => 'Milk']);
    $other = createList($household);
    addItem($this, $other, ['name' => 'Secret Medicine']);

    $this->getJson(route('lists.suggestions', $list))->assertJsonCount(2, 'data');

    $this->actingAs($guest);
    $personal = createList($guest);
    addItem($this, $personal, ['name' => 'Guest Soda']);

    $names = collect($this->getJson(route('lists.suggestions', $list))->json('data'))->pluck('name');

    expect($names)->toContain('Milk', 'Guest Soda')->not->toContain('Secret Medicine');
});

it('revokes guest access immediately and lets guests leave', function () {
    $owner = User::factory()->create();
    $list = createList($owner);
    $guest = shareList($list, ListShareRole::Editor);
    $leaver = shareList($list, ListShareRole::Viewer);

    $this->actingAs($owner);
    $this->patch(route('lists.shares.update', [$list, $guest]), ['role' => 'viewer'])->assertRedirect();
    $this->delete(route('lists.shares.destroy', [$list, $guest]))->assertRedirect();

    $this->actingAs($guest)->get(route('lists.show', $list))->assertNotFound();

    $this->actingAs($leaver)->delete(route('lists.shares.destroy', [$list, $leaver]))->assertRedirect(route('lists.index'));
    $this->get(route('lists.show', $list))->assertNotFound();
});

it('ends guest access when the household is deleted', function () {
    $household = Household::factory()->create();
    $list = createList($household);
    $guest = shareList($list, ListShareRole::Editor);

    $household->delete();

    $this->actingAs($guest);
    $this->get(route('lists.show', $list))->assertNotFound();
    expect(listsPage($this))->toHaveCount(0);
});

it('runs the list share invitation flow for the invited email only', function () {
    Notification::fake();
    $owner = User::factory()->create();
    $list = createList($owner);
    $invited = User::factory()->create(['email' => 'guest@example.com']);

    $this->actingAs($owner);
    $this->post(route('lists.shares.store', $list), ['email' => 'Guest@Example.com', 'role' => 'viewer'])->assertSessionHasNoErrors();
    $this->post(route('lists.shares.store', $list), ['email' => $owner->email])->assertRedirect();
    expectConflictToast();

    Notification::assertSentTo(new AnonymousNotifiable, ListShareInvitationNotification::class);
    $invitation = ListShareInvitation::query()->firstOrFail();

    $this->actingAs(User::factory()->create())->post(route('invitations.list.accept', $invitation))->assertNotFound();

    $this->actingAs($invited);
    expect(pageProps($this->get(route('dashboard')), 'listInvitations'))->toHaveCount(1);
    $this->post(route('invitations.list.accept', $invitation))->assertRedirect(route('lists.show', $list));
    expect(listPage($this, $list)['can']['edit_items'])->toBeFalse();
    $this->post(route('invitations.list.accept', $invitation))->assertRedirect();
    expectConflictToast();
});

it('lists only deleted lists the user may restore', function () {
    $household = Household::factory()->create();
    $creator = User::factory()->create();
    $member = User::factory()->create();
    $household->addMember($creator, HouseholdRole::Member);
    $household->addMember($member, HouseholdRole::Member);
    $list = createList($household, $creator);
    $guest = shareList($list, ListShareRole::Editor);
    $list->delete();
    createList($household, $creator);

    foreach ([$creator, $household->owner] as $allowed) {
        $deleted = listsPage($this->actingAs($allowed), ['status' => 'deleted']);
        expect($deleted)->toHaveCount(1)
            ->and($deleted[0]['uuid'])->toBe($list->uuid)
            ->and($deleted[0]['can']['restore'])->toBeTrue();
    }

    foreach ([$member, $guest, User::factory()->create()] as $denied) {
        expect(listsPage($this->actingAs($denied), ['status' => 'deleted']))->toHaveCount(0);
    }
});

it('tells guests they can leave, and members they cannot', function () {
    $owner = User::factory()->create();
    $list = createList($owner);
    $guest = shareList($list, ListShareRole::Viewer);

    expect(listPage($this->actingAs($guest), $list)['can']['leave'])->toBeTrue();

    expect(listPage($this->actingAs($owner), $list)['can']['leave'])->toBeFalse();
});

it('audits list invitation revocation once and only while pending', function () {
    Notification::fake();
    $owner = User::factory()->create();
    $list = createList($owner);
    $this->actingAs($owner);

    $this->post(route('lists.shares.store', $list), ['email' => 'guest@example.com'])->assertSessionHasNoErrors();
    $invitation = ListShareInvitation::query()->sole();

    $this->delete(route('lists.share-invitations.destroy', [$list, $invitation]))->assertRedirect();
    $this->delete(route('lists.share-invitations.destroy', [$list, $invitation]))->assertRedirect();

    expect(AuditLog::query()->where('action', 'list.share_invitation_revoked')->count())->toBe(1);
});

it('marks a checked-off item needed again instead of duplicating it', function () {
    $user = User::factory()->create();
    $list = createList($user);
    $this->actingAs($user);

    $uuid = addItem($this, $list, ['name' => '2 lb apples', 'notes' => 'gala'])['uuid'];
    $this->patch(route('lists.items.update', [$list, $uuid]), ['status' => 'satisfied']);

    expect(addItem($this, $list, ['name' => 'Apples']))
        ->uuid->toBe($uuid)
        ->status->toBe('needed')
        ->quantity->toBe('2.000')
        ->notes->toBe('gala')
        ->revision->toBe(3);

    $this->patch(route('lists.items.update', [$list, $uuid]), ['status' => 'satisfied']);
    expect(addItem($this, $list, ['name' => '5 lb apples'])['quantity'])->toBe('5.000');

    expect(listItems($this, $list))->toHaveCount(1);
});

it('still allows a second copy while the first is needed', function () {
    $user = User::factory()->create();
    $list = createList($user);
    $this->actingAs($user);

    addItem($this, $list, ['name' => 'milk']);
    addItem($this, $list, ['name' => 'milk']);

    expect(listItems($this, $list))->toHaveCount(2);
});
