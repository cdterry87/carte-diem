<?php

use App\Actions\Trips\CompleteShoppingTrip;
use App\Enums\HouseholdRole;
use App\Enums\ListShareRole;
use App\Enums\TripStatus;
use App\Events\TripCompleted;
use App\Models\Household;
use App\Models\PurchaseTransaction;
use App\Models\PurchaseTransactionItem;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\ShoppingTrip;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Event;

function tripList(Model $owner, array $items = ['milk', 'eggs']): ShoppingList
{
    $list = ShoppingList::query()->create([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
        'title' => 'Groceries',
    ]);

    foreach ($items as $index => $item) {
        $list->items()->create(['name_snapshot' => $item, 'sort_order' => $index + 1]);
    }

    return $list;
}

/**
 * The trip as its page shows it.
 *
 * @return array<string, mixed>
 */
function tripPage($test, string $uuid): array
{
    return pageProps($test->get(route('trips.show', $uuid)), 'trip');
}

/**
 * @return array<string, mixed>
 */
function startTrip($test, ShoppingList $list, array $payload = []): array
{
    $test->post(route('trips.store', $list), $payload)->assertSessionHasNoErrors()->assertRedirect();

    return tripPage($test, ShoppingTrip::query()->where('shopping_list_id', $list->id)->latest('id')->firstOrFail()->uuid);
}

function checkItem($test, array $trip, string $name, array $extra = []): void
{
    $item = collect($trip['items'])->firstWhere('name', $name);
    $test->patch(route('trips.items.update', [$trip['uuid'], $item['uuid']]), ['purchased' => true, ...$extra])->assertSessionHasNoErrors()->assertRedirect();
}

function completeTrip($test, array $trip): void
{
    $test->post(route('trips.complete', $trip['uuid']))->assertRedirect();
}

function listItem(ShoppingList $list, string $name): ShoppingListItem
{
    return $list->items()->where('name_snapshot', $name)->firstOrFail();
}

it('snapshots only needed items and uses the list store by default', function () {
    $user = User::factory()->create();
    $list = tripList($user);
    $store = Store::query()->create(['scope_type' => 'user', 'scope_id' => $user->id, 'name' => 'Corner']);
    $list->update(['default_store_id' => $store->id]);
    listItem($list, 'eggs')->update(['status' => 'satisfied']);
    $this->actingAs($user);

    $trip = startTrip($this, $list);

    expect($trip['status'])->toBe('active')
        ->and($trip['store']['name'])->toBe('Corner')
        ->and($trip['items'])->toHaveCount(1)
        ->and($trip['items'][0]['name'])->toBe('milk');
});

it('can snapshot a selected subset', function () {
    $user = User::factory()->create();
    $list = tripList($user);
    $this->actingAs($user);

    $trip = startTrip($this, $list, ['items' => [listItem($list, 'eggs')->uuid]]);

    expect($trip['items'])->toHaveCount(1)->and($trip['items'][0]['name'])->toBe('eggs');
    expect(collect($trip['added_since'])->pluck('name')->all())->toBe(['milk']);
});

it('refuses a store from another scope, empty lists, and archived lists', function () {
    $user = User::factory()->create();
    $list = tripList($user);
    $foreign = Store::query()->create(['scope_type' => 'user', 'scope_id' => User::factory()->create()->id, 'name' => 'X']);
    $this->actingAs($user);

    $this->post(route('trips.store', $list), ['store' => $foreign->uuid])->assertSessionHasErrors('store');

    $empty = tripList($user, []);
    $this->post(route('trips.store', $empty))->assertSessionHasErrors();

    $list->update(['status' => 'archived']);
    $this->post(route('trips.store', $list))->assertRedirect();
    expectConflictToast();
    expect(ShoppingTrip::query()->count())->toBe(0);
});

it('allows one active trip per list and sends people to the existing one', function () {
    $user = User::factory()->create();
    $list = tripList($user);
    $this->actingAs($user);

    $trip = startTrip($this, $list);

    $this->post(route('trips.store', $list))->assertRedirect(route('trips.show', $trip['uuid']));
    expect(ShoppingTrip::query()->count())->toBe(1);

    expect(pageProps($this->get(route('lists.show', $list)), 'list.active_trip'))->toBe($trip['uuid']);
});

it('enforces one active trip at the database level', function () {
    $list = tripList(User::factory()->create());
    $attributes = ['shopping_list_id' => $list->id, 'started_at' => now()];

    ShoppingTrip::query()->create($attributes);

    expect(fn () => ShoppingTrip::query()->create($attributes))->toThrow(UniqueConstraintViolationException::class);
});

it('allows a new trip after the previous one ends', function () {
    $user = User::factory()->create();
    $list = tripList($user);
    $this->actingAs($user);

    $first = startTrip($this, $list);
    $this->post(route('trips.cancel', $first['uuid']))->assertRedirect(route('lists.show', $list));
    expect(tripPage($this, $first['uuid'])['status'])->toBe('cancelled');

    startTrip($this, $list);
});

it('records who checked an item and clears it on uncheck', function () {
    $user = User::factory()->create();
    $list = tripList($user);
    $this->actingAs($user);
    $trip = startTrip($this, $list);
    $item = $trip['items'][0];

    $this->patch(route('trips.items.update', [$trip['uuid'], $item['uuid']]), ['purchased' => true])->assertRedirect();
    $items = collect(tripPage($this, $trip['uuid'])['items'])->keyBy('uuid');
    expect($items[$item['uuid']]['purchased'])->toBeTrue()
        ->and($items[$item['uuid']]['checked_by'])->toBe($user->name)
        ->and($items->where('purchased', false))->toHaveCount(1);

    $this->patch(route('trips.items.update', [$trip['uuid'], $item['uuid']]), ['purchased' => false])->assertRedirect();
    expect(collect(tripPage($this, $trip['uuid'])['items'])->firstWhere('uuid', $item['uuid'])['checked_at'])->toBeNull();
});

it('refuses stale trip item revisions', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $trip = startTrip($this, tripList($user));
    $item = $trip['items'][0];

    $this->patch(route('trips.items.update', [$trip['uuid'], $item['uuid']]), ['purchased' => true, 'revision' => 1])->assertRedirect();
    $this->patch(route('trips.items.update', [$trip['uuid'], $item['uuid']]), ['purchased' => false, 'revision' => 1])->assertRedirect();
    expectConflictToast();
    expect(collect(tripPage($this, $trip['uuid'])['items'])->firstWhere('uuid', $item['uuid'])['purchased'])->toBeTrue();
});

it('completes a trip into one verified purchase with null totals', function () {
    Event::fake([TripCompleted::class]);
    $user = User::factory()->create();
    $list = tripList($user);
    $this->actingAs($user);
    $trip = startTrip($this, $list);
    checkItem($this, $trip, 'milk');

    completeTrip($this, $trip);
    $done = tripPage($this, $trip['uuid']);

    expect($done['status'])->toBe('completed')->and($done['completed_by'])->toBe($user->name)->and($done['purchase'])->toBeString();

    $purchase = PurchaseTransaction::query()->sole();
    expect($purchase->entry_method->value)->toBe('trip')
        ->and($purchase->verification_status->value)->toBe('verified')
        ->and($purchase->total_minor)->toBeNull()
        ->and($purchase->items()->sole()->description_snapshot)->toBe('milk');

    expect(listItem($list, 'milk')->status->value)->toBe('satisfied')
        ->and(listItem($list, 'eggs')->status->value)->toBe('needed');

    Event::assertDispatched(TripCompleted::class);
});

it('is idempotent when completion repeats', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $trip = startTrip($this, tripList($user));
    checkItem($this, $trip, 'milk');

    completeTrip($this, $trip);
    $first = tripPage($this, $trip['uuid']);
    completeTrip($this, $trip);
    $second = tripPage($this, $trip['uuid']);

    expect($second['purchase'])->toBe($first['purchase'])->and($second['completed_at'])->toBe($first['completed_at']);
    expect(PurchaseTransaction::query()->count())->toBe(1)->and(PurchaseTransactionItem::query()->count())->toBe(1);
});

it('cannot write two transactions for one trip even past the status guard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $trip = startTrip($this, tripList($user));
    checkItem($this, $trip, 'milk');
    completeTrip($this, $trip);

    $model = ShoppingTrip::query()->where('uuid', $trip['uuid'])->firstOrFail();
    $model->update(['status' => TripStatus::Active]);

    expect(fn () => app(CompleteShoppingTrip::class)->handle($model, $user))->toThrow(UniqueConstraintViolationException::class);
    expect(PurchaseTransaction::query()->count())->toBe(1);
});

it('completes with nothing purchased without writing a purchase', function () {
    $user = User::factory()->create();
    $list = tripList($user);
    $this->actingAs($user);
    $trip = startTrip($this, $list);

    completeTrip($this, $trip);
    expect(tripPage($this, $trip['uuid'])['status'])->toBe('completed');

    expect(PurchaseTransaction::query()->count())->toBe(0)
        ->and(listItem($list, 'milk')->status->value)->toBe('needed');
});

it('freezes a trip once it ends', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $trip = startTrip($this, tripList($user));
    $item = $trip['items'][0];

    completeTrip($this, $trip);

    $this->patch(route('trips.items.update', [$trip['uuid'], $item['uuid']]), ['purchased' => true])->assertRedirect();
    expectConflictToast();
    $this->post(route('trips.cancel', $trip['uuid']))->assertRedirect();
    expectConflictToast();
    expect(tripPage($this, $trip['uuid'])['status'])->toBe('completed');
});

it('discards checks on cancel and refuses to complete afterwards', function () {
    $user = User::factory()->create();
    $list = tripList($user);
    $this->actingAs($user);
    $trip = startTrip($this, $list);
    checkItem($this, $trip, 'milk');

    $this->post(route('trips.cancel', $trip['uuid']))->assertRedirect();
    $this->post(route('trips.cancel', $trip['uuid']))->assertRedirect();
    expect(flashedToast()['type'] ?? null)->toBe('success');
    $this->post(route('trips.complete', $trip['uuid']))->assertRedirect();
    expectConflictToast();

    expect(PurchaseTransaction::query()->count())->toBe(0)
        ->and(listItem($list, 'milk')->status->value)->toBe('needed');
});

it('keeps the remainder of a partial purchase needed', function () {
    $user = User::factory()->create();
    $list = tripList($user);
    listItem($list, 'milk')->update(['requested_quantity' => 3, 'unit_code' => 'gal']);
    $this->actingAs($user);
    $trip = startTrip($this, $list);
    checkItem($this, $trip, 'milk', ['actual_quantity' => 2]);

    completeTrip($this, $trip);

    $item = listItem($list, 'milk');
    expect($item->status->value)->toBe('needed')
        ->and($item->requested_quantity)->toBe('1.000')
        ->and($item->needs_review)->toBeFalse();
    expect(PurchaseTransactionItem::query()->sole()->quantity)->toBe('2.000');
});

it('satisfies an item when buying at least the requested amount', function () {
    $user = User::factory()->create();
    $list = tripList($user);
    listItem($list, 'milk')->update(['requested_quantity' => 2]);
    $this->actingAs($user);
    $trip = startTrip($this, $list);
    checkItem($this, $trip, 'milk', ['actual_quantity' => 5]);

    completeTrip($this, $trip);

    $item = listItem($list, 'milk');
    expect($item->status->value)->toBe('satisfied')->and($item->requested_quantity)->toBe('2.000');
});

it('keeps an item needed when its requested quantity rose during the trip', function () {
    $user = User::factory()->create();
    $list = tripList($user);
    listItem($list, 'milk')->update(['requested_quantity' => 1]);
    $this->actingAs($user);
    $trip = startTrip($this, $list);
    checkItem($this, $trip, 'milk');
    listItem($list, 'milk')->update(['requested_quantity' => 3]);

    completeTrip($this, $trip);

    $item = listItem($list, 'milk');
    expect($item->status->value)->toBe('needed')->and($item->requested_quantity)->toBe('2.000');
});

it('flags an item when its unit changed during the trip', function () {
    $user = User::factory()->create();
    $list = tripList($user);
    listItem($list, 'milk')->update(['requested_quantity' => 2, 'unit_code' => 'gal']);
    $this->actingAs($user);
    $trip = startTrip($this, $list);
    checkItem($this, $trip, 'milk');
    listItem($list, 'milk')->update(['unit_code' => 'qt']);

    completeTrip($this, $trip);

    $item = listItem($list, 'milk');
    expect($item->status->value)->toBe('needed')
        ->and($item->requested_quantity)->toBe('2.000')
        ->and($item->needs_review)->toBeTrue();
});

it('leaves additions and deletions made during the trip alone', function () {
    $user = User::factory()->create();
    $list = tripList($user);
    $this->actingAs($user);
    $trip = startTrip($this, $list);
    checkItem($this, $trip, 'milk');
    checkItem($this, $trip, 'eggs');

    $this->post(route('lists.items.store', $list), ['name' => 'bread'])->assertSessionHasNoErrors();
    listItem($list, 'eggs')->delete();

    $shown = tripPage($this, $trip['uuid']);
    expect(collect($shown['added_since'])->pluck('name')->all())->toBe(['bread']);

    completeTrip($this, $trip);

    expect(listItem($list, 'bread')->status->value)->toBe('needed');
    expect($list->items()->withTrashed()->where('name_snapshot', 'eggs')->first()->trashed())->toBeTrue();
    expect(PurchaseTransactionItem::query()->count())->toBe(2);
});

it('includes a later list item only on explicit request', function () {
    $user = User::factory()->create();
    $list = tripList($user);
    $this->actingAs($user);
    $trip = startTrip($this, $list);
    $this->post(route('lists.items.store', $list), ['name' => 'bread'])->assertSessionHasNoErrors();
    $bread = listItem($list, 'bread')->uuid;

    expect(tripPage($this, $trip['uuid'])['items'])->toHaveCount(2);

    $this->post(route('trips.items.include', $trip['uuid']), ['item' => $bread])->assertRedirect();
    $this->post(route('trips.items.include', $trip['uuid']), ['item' => $bread])->assertRedirect();

    $shown = tripPage($this, $trip['uuid']);
    expect($shown['items'])->toHaveCount(3)->and($shown['added_since'])->toBe([]);
});

it('keeps history unchanged by later list edits', function () {
    $user = User::factory()->create();
    $list = tripList($user);
    $this->actingAs($user);
    $trip = startTrip($this, $list);
    checkItem($this, $trip, 'milk');
    completeTrip($this, $trip);

    listItem($list, 'milk')->update(['name_snapshot' => 'oat milk']);
    $list->items()->where('name_snapshot', 'oat milk')->first()->delete();
    $list->delete();

    expect(collect(tripPage($this, $trip['uuid'])['items'])->pluck('name'))->toContain('milk');
    expect(pageProps($this->get(route('purchases.index')), 'purchases.0.item_count'))->toBe(1);
});

it('marks a purchased item needed again after completion', function () {
    $user = User::factory()->create();
    $list = tripList($user);
    $this->actingAs($user);
    $trip = startTrip($this, $list);
    checkItem($this, $trip, 'milk');
    completeTrip($this, $trip);

    $this->patch(route('lists.items.update', [$list, listItem($list, 'milk')]), ['status' => 'needed'])->assertSessionHasNoErrors();
    expect(listItem($list, 'milk')->status->value)->toBe('needed');

    expect(PurchaseTransaction::query()->count())->toBe(1);
});

it('keeps guest completion in the owner household, hidden from the guest', function () {
    $household = Household::factory()->create();
    $list = tripList($household);
    $editor = User::factory()->create();
    $list->shares()->create(['user_id' => $editor->id, 'role' => ListShareRole::Editor]);

    $this->actingAs($editor);
    $trip = startTrip($this, $list);
    checkItem($this, $trip, 'milk');
    completeTrip($this, $trip);

    expect(tripPage($this, $trip['uuid']))->not->toHaveKey('purchase');
    $purchase = PurchaseTransaction::query()->sole();
    expect($purchase->owner_type)->toBe('household')->and($purchase->owner_id)->toBe($household->id);

    expect(pageProps($this->get(route('purchases.index')), 'purchases'))->toHaveCount(0);
    $this->get(route('purchases.index', ['household' => $household->uuid]))->assertNotFound();
    $this->get(route('purchases.show', $purchase))->assertNotFound();
    expect(pageProps($this->get(route('history.index')), 'trips'))->toHaveCount(1);

    $this->actingAs($household->owner);
    expect(pageProps($this->get(route('purchases.index', ['household' => $household->uuid])), 'purchases'))->toHaveCount(1);
    expect(pageProps($this->get(route('purchases.show', $purchase)), 'purchase.items.0.description'))->toBe('milk');
});

it('blocks viewers from running trips', function () {
    $list = tripList(User::factory()->create());
    $viewer = User::factory()->create();
    $list->shares()->create(['user_id' => $viewer->id, 'role' => ListShareRole::Viewer]);

    $this->actingAs($viewer);
    $this->post(route('trips.store', $list))->assertForbidden();
});

it('rejects cross-household access to trips and purchases', function () {
    $household = Household::factory()->create();
    $list = tripList($household);
    $this->actingAs($household->owner);
    $trip = startTrip($this, $list);
    checkItem($this, $trip, 'milk');
    $item = $trip['items'][0];

    $this->actingAs(User::factory()->create());
    $this->get(route('trips.show', $trip['uuid']))->assertNotFound();
    $this->patch(route('trips.items.update', [$trip['uuid'], $item['uuid']]), ['purchased' => false])->assertNotFound();
    $this->post(route('trips.complete', $trip['uuid']))->assertNotFound();
    $this->post(route('trips.cancel', $trip['uuid']))->assertNotFound();
    expect(pageProps($this->get(route('history.index')), 'trips'))->toHaveCount(0);

    $this->actingAs($household->owner);
    completeTrip($this, $trip);
    $purchase = PurchaseTransaction::query()->sole();

    $this->actingAs(User::factory()->create());
    $this->get(route('purchases.show', $purchase))->assertNotFound();
    $this->get(route('purchases.index', ['household' => $household->uuid]))->assertNotFound();
});

it('keeps household history in the household after a member leaves', function () {
    $household = Household::factory()->create();
    $member = User::factory()->create();
    $household->addMember($member, HouseholdRole::Member);
    $list = tripList($household);

    $this->actingAs($member);
    $trip = startTrip($this, $list);
    checkItem($this, $trip, 'milk');
    completeTrip($this, $trip);
    $this->post(route('households.leave', $household))->assertRedirect();

    $this->get(route('purchases.index', ['household' => $household->uuid]))->assertNotFound();
    $this->get(route('trips.show', $trip['uuid']))->assertNotFound();

    $this->actingAs($household->owner);
    expect(pageProps($this->get(route('purchases.index', ['household' => $household->uuid])), 'purchases'))->toHaveCount(1);
});

it('filters history by scope', function () {
    $user = User::factory()->create();
    $household = Household::factory()->create(['owner_user_id' => $user->id]);
    $this->actingAs($user);

    foreach ([tripList($user), tripList($household)] as $list) {
        $trip = startTrip($this, $list);
        checkItem($this, $trip, 'milk');
        completeTrip($this, $trip);
    }

    expect(pageProps($this->get(route('history.index')), 'trips'))->toHaveCount(2);
    expect(pageProps($this->get(route('history.index', ['scope' => 'personal'])), 'trips'))->toHaveCount(1);
    expect(pageProps($this->get(route('history.index', ['scope' => $household->uuid])), 'trips'))->toHaveCount(1);
    expect(pageProps($this->get(route('purchases.index')), 'purchases'))->toHaveCount(1);
    expect(pageProps($this->get(route('purchases.index', ['household' => $household->uuid])), 'purchases'))->toHaveCount(1);
});

it('anonymizes authorship in surviving households when an account is deleted', function () {
    $household = Household::factory()->create();
    $member = User::factory()->create();
    $household->addMember($member, HouseholdRole::Member);
    $list = tripList($household);

    $this->actingAs($member);
    $this->post(route('lists.items.store', $list), ['name' => 'bread']);
    $trip = startTrip($this, $list);
    checkItem($this, $trip, 'milk');
    completeTrip($this, $trip);

    $personal = tripList($member);
    $personalTrip = startTrip($this, $personal);
    checkItem($this, $personalTrip, 'milk');
    completeTrip($this, $personalTrip);

    $this->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');

    $purchase = PurchaseTransaction::query()->sole();
    expect($purchase->owner_id)->toBe($household->id)
        ->and($purchase->getAttribute('created_by'))->toBeNull()
        ->and(ShoppingList::withTrashed()->find($personal->id))->toBeNull();
});

it('records bought quantity and a note for an item with no planned quantity', function () {
    $user = User::factory()->create();
    $list = tripList($user);
    $this->actingAs($user);
    $trip = startTrip($this, $list);
    checkItem($this, $trip, 'milk', ['actual_quantity' => 2, 'notes' => 'store brand']);

    completeTrip($this, $trip);

    expect(PurchaseTransactionItem::query()->sole()->quantity)->toBe('2.000')
        ->and(listItem($list, 'milk')->status->value)->toBe('satisfied')
        ->and(listItem($list, 'milk')->notes)->toBeNull();
    expect(collect(tripPage($this, $trip['uuid'])['items'])->firstWhere('name', 'milk')['notes'])->toBe('store brand');
});
