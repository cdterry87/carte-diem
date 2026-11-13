<?php

use App\Models\Category;
use App\Models\ShoppingList;
use App\Models\ShoppingTrip;
use App\Models\StapleRule;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Inertia\Testing\AssertableInertia as Assert;

function storeOrderList(Model $owner): ShoppingList
{
    $list = ShoppingList::query()->create(['owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey(), 'title' => 'Weekly']);
    $list->items()->create(['name_snapshot' => 'apples', 'requested_quantity' => 2, 'unit_code' => 'lb', 'status' => 'satisfied', 'sort_order' => 1]);
    $list->items()->create(['name_snapshot' => 'milk', 'notes' => 'whole', 'sort_order' => 2]);

    return $list;
}

function category(string $slug): Category
{
    return Category::query()->where('scope_type', 'system')->where('slug', $slug)->sole();
}

it('sorts a trip by the chosen store aisle order, with unplaced categories after', function () {
    $user = User::factory()->create();
    $store = Store::query()->create(['scope_type' => 'user', 'scope_id' => $user->id, 'name' => 'Corner']);
    $list = ShoppingList::query()->create(['owner_type' => 'user', 'owner_id' => $user->id, 'title' => 'L']);
    foreach (['produce' => 'apples', 'dairy-eggs' => 'milk', 'frozen' => 'peas'] as $slug => $name) {
        $list->items()->create(['name_snapshot' => $name, 'category_id' => category($slug)->id]);
    }
    $this->actingAs($user);
    $sorted = fn (ShoppingTrip $trip): array => $trip->items()->orderBy('category_sort_snapshot')->pluck('name_snapshot')->all();

    $this->put(route('organize.stores.category-order', $store), ['categories' => [category('frozen')->uuid, category('dairy-eggs')->uuid]])->assertRedirect();

    $this->post(route('trips.store', $list), ['store' => $store->uuid])->assertRedirect();
    $trip = ShoppingTrip::query()->latest('id')->firstOrFail();
    expect($sorted($trip))->toBe(['peas', 'milk', 'apples']);

    $this->post(route('trips.cancel', $trip))->assertRedirect();
    $this->post(route('trips.store', $list))->assertRedirect();
    expect($sorted(ShoppingTrip::query()->latest('id')->firstOrFail()))->toBe(['apples', 'milk', 'peas']);
});

it('rejects aisle order for other scopes and foreign categories', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $store = Store::query()->create(['scope_type' => 'user', 'scope_id' => $user->id, 'name' => 'Corner']);
    $foreign = Category::query()->create(['name' => 'Foreign', 'slug' => 'foreign', 'scope_type' => 'user', 'scope_id' => $other->id]);

    $this->actingAs($other)->put(route('organize.stores.category-order', $store), ['categories' => []])->assertNotFound();

    $this->actingAs($user)->put(route('organize.stores.category-order', $store), ['categories' => [$foreign->uuid]])->assertSessionHasErrors();
});

it('passes the default store order to the list page', function () {
    $user = User::factory()->create();
    $store = Store::query()->create(['scope_type' => 'user', 'scope_id' => $user->id, 'name' => 'Corner']);
    $list = storeOrderList($user);
    $list->update(['default_store_id' => $store->id]);
    $frozen = category('frozen');

    $this->actingAs($user)->put(route('organize.stores.category-order', $store), ['categories' => [$frozen->uuid]])->assertRedirect();

    $this->get(route('lists.show', $list))->assertInertia(fn (Assert $page) => $page->where("categoryOrder.{$frozen->uuid}", 1));
    $this->get(route('organize.index'))->assertInertia(fn (Assert $page) => $page->where("storeOrders.{$store->uuid}.0", $frozen->uuid));
});

it('purges staples and trips with the account', function () {
    $user = User::factory()->create();
    $list = storeOrderList($user);
    $this->actingAs($user);
    $this->post(route('staples.store'), ['owner' => ['type' => 'user'], 'name' => 'Coffee'])->assertRedirect();
    $this->post(route('trips.store', $list))->assertRedirect();
    expect(StapleRule::query()->count())->toBe(1)
        ->and(ShoppingTrip::query()->count())->toBe(1);

    $this->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');

    expect(StapleRule::query()->count())->toBe(0)
        ->and(ShoppingTrip::query()->count())->toBe(0);
});
