<?php

use App\Enums\HouseholdRole;
use App\Enums\ListShareRole;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\ListShareInvitation;
use App\Models\Product;
use App\Models\PurchaseTransaction;
use App\Models\ShoppingList;
use App\Models\ShoppingTrip;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Inertia\Testing\AssertableInertia as Assert;

function webList(Model $owner, ?User $creator = null): ShoppingList
{
    $list = ShoppingList::query()->create([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
        'title' => 'Groceries',
        'created_by' => $creator?->id,
    ]);
    $list->items()->create(['name_snapshot' => 'milk', 'sort_order' => 1]);

    return $list;
}

it('redirects guests and unverified users away from the app', function () {
    $this->get(route('lists.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('lists.index'))
        ->assertRedirect(route('verification.notice'));
});

it('renders every main page', function () {
    $user = User::factory()->create();
    $household = Household::factory()->create(['owner_user_id' => $user->id]);
    $list = webList($household);

    $this->actingAs($user);

    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->component('dashboard')->has('lists', 1)->has('workspaces', 2));
    $this->get(route('lists.index'))->assertInertia(fn (Assert $page) => $page->component('lists/index')->has('lists', 1));
    $this->get(route('lists.show', $list))->assertInertia(fn (Assert $page) => $page
        ->component('lists/show')
        ->where('list.uuid', $list->uuid)
        ->where('items.0.name', 'milk')
        ->has('categories', 11)
        ->missing('list.id'));
    $this->get(route('history.index'))->assertInertia(fn (Assert $page) => $page->component('history/index'));
    $this->get(route('households.index'))->assertInertia(fn (Assert $page) => $page->component('households/index')->has('households', 1));
    $this->get(route('households.show', $household))->assertInertia(fn (Assert $page) => $page->component('households/settings')->where('can.delete', true));
    $this->get(route('organize.index'))->assertInertia(fn (Assert $page) => $page->component('organize/index')->where('owner.type', 'user'));
    $this->get(route('organize.index', ['household' => $household->uuid]))->assertInertia(fn (Assert $page) => $page->where('owner.type', 'household'));
});

it('hides other scopes on web routes', function () {
    $household = Household::factory()->create();
    $list = webList($household);

    $this->actingAs(User::factory()->create());

    $this->get(route('lists.show', $list))->assertNotFound();
    $this->get(route('households.show', $household))->assertNotFound();
    $this->get(route('organize.index', ['household' => $household->uuid]))->assertNotFound();
});

it('runs a whole shopping flow through web routes', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post(route('lists.store'), ['title' => 'Weekly', 'owner' => ['type' => 'user']])->assertRedirect();
    $list = ShoppingList::query()->sole();

    $this->post(route('lists.items.store', $list), ['name' => '2 lb apples'])->assertRedirect();
    $this->post(route('lists.items.store', $list), ['name' => 'milk'])->assertRedirect();

    $this->post(route('trips.store', $list))->assertRedirect();
    $trip = ShoppingTrip::query()->sole();
    $this->post(route('trips.store', $list))->assertRedirect(route('trips.show', $trip));

    $this->get(route('trips.show', $trip))->assertInertia(fn (Assert $page) => $page
        ->component('trips/show')
        ->has('trip.items', 2)
        ->where('trip.can.run', true));

    $item = $trip->items()->where('name_snapshot', 'apples')->firstOrFail();
    $this->patch(route('trips.items.update', [$trip, $item->uuid]), ['purchased' => true, 'actual_quantity' => 1])->assertRedirect();
    $this->post(route('trips.complete', $trip))->assertRedirect();
    $this->post(route('trips.complete', $trip))->assertRedirect();

    $purchase = PurchaseTransaction::query()->sole();

    $this->get(route('history.index'))->assertInertia(fn (Assert $page) => $page->has('trips', 1)->where('trips.0.purchased_count', 1));
    $this->get(route('purchases.show', $purchase))->assertInertia(fn (Assert $page) => $page->component('purchases/show')->where('purchase.items.0.description', 'apples'));

    expect($list->items()->where('name_snapshot', 'apples')->first()->requested_quantity)->toBe('1.000');
    expect($list->items()->where('name_snapshot', 'milk')->first()->status->value)->toBe('needed');
});

it('flashes a toast instead of failing on a web conflict', function () {
    $user = User::factory()->create();
    $list = webList($user);
    $trip = ShoppingTrip::query()->create(['shopping_list_id' => $list->id, 'started_at' => now(), 'status' => 'cancelled']);
    $this->actingAs($user);

    $this->from(route('trips.show', $trip))
        ->post(route('trips.complete', $trip))
        ->assertRedirect(route('trips.show', $trip));

    expect($trip->refresh()->status->value)->toBe('cancelled');
});

it('shows an invitation by token only to the invited email', function () {
    $household = Household::factory()->create();
    $invited = User::factory()->create(['email' => 'invited@example.com']);
    $token = 'household-token';
    $invitation = $household->invitations()->create([
        'email' => 'invited@example.com',
        'role' => HouseholdRole::Member,
        'token_hash' => hash('sha256', $token),
        'expires_at' => now()->addDay(),
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('invitations.show', $token))
        ->assertInertia(fn (Assert $page) => $page->component('invitations/show')->where('invitation', null));

    $this->actingAs($invited)
        ->get(route('invitations.show', 'wrong-token'))
        ->assertInertia(fn (Assert $page) => $page->where('invitation', null));

    $this->get(route('invitations.show', $token))
        ->assertInertia(fn (Assert $page) => $page->where('invitation.name', $household->name));

    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->has('householdInvitations', 1));

    $this->post(route('invitations.household.accept', $invitation))->assertRedirect(route('households.show', $household));
    expect($invited->roleIn($household))->toBe(HouseholdRole::Member);
    expect(HouseholdInvitation::query()->sole()->accepted_at)->not->toBeNull();

    $this->get(route('invitations.show', $token))->assertInertia(fn (Assert $page) => $page->where('invitation', null));
});

it('shows and accepts list invitations by token', function () {
    $list = webList(User::factory()->create());
    $invited = User::factory()->create(['email' => 'guest@example.com']);
    $token = 'list-token';
    $invitation = ListShareInvitation::query()->create([
        'shopping_list_id' => $list->id,
        'email' => 'guest@example.com',
        'role' => ListShareRole::Viewer,
        'token_hash' => hash('sha256', $token),
        'expires_at' => now()->addDay(),
    ]);

    $this->actingAs($invited)
        ->get(route('list-invitations.show', $token))
        ->assertInertia(fn (Assert $page) => $page->where('invitation.type', 'list'));

    $this->post(route('invitations.list.accept', $invitation))->assertRedirect(route('lists.show', $list));

    $this->get(route('lists.show', $list))->assertInertia(fn (Assert $page) => $page
        ->where('list.can.edit_items', false)
        ->where('stores', [])
        ->where('shares', []));
});

it('blocks web account deletion while owning a shared household', function () {
    $household = Household::factory()->create();
    $household->addMember(User::factory()->create(), HouseholdRole::Member);

    $this->actingAs($household->owner)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect(route('profile.edit'));

    expect(User::query()->find($household->owner_user_id))->not->toBeNull();
    $this->assertAuthenticatedAs($household->owner);
});

it('starts a trip at a chosen store or at no store', function () {
    $user = User::factory()->create();
    $list = webList($user);
    $default = Store::query()->create(['scope_type' => 'user', 'scope_id' => $user->id, 'name' => 'Default']);
    $chosen = Store::query()->create(['scope_type' => 'user', 'scope_id' => $user->id, 'name' => 'Chosen']);
    $list->update(['default_store_id' => $default->id]);
    $this->actingAs($user);

    $this->post(route('trips.store', $list), ['store' => $chosen->uuid])->assertRedirect();
    $trip = ShoppingTrip::query()->sole();
    expect($trip->store_id)->toBe($chosen->id);

    $this->post(route('trips.cancel', $trip));
    $this->post(route('trips.store', $list), ['store' => null, 'no_store' => true])->assertRedirect();
    expect(ShoppingTrip::query()->where('status', 'active')->sole()->store_id)->toBeNull();
});

it('toggles item favorites per user in scope only', function () {
    $user = User::factory()->create();
    $list = webList($user);
    $this->actingAs($user)->post(route('lists.items.store', $list), ['name' => 'coffee']);
    $product = Product::query()->sole();

    $this->post(route('products.favorite', $product))->assertRedirect();
    $this->get(route('lists.show', $list))->assertInertia(fn (Assert $page) => $page->where('favoriteProducts.0', $product->uuid));

    $this->post(route('products.favorite', $product));
    $this->get(route('lists.show', $list))->assertInertia(fn (Assert $page) => $page->where('favoriteProducts', []));

    $this->actingAs(User::factory()->create())->post(route('products.favorite', $product))->assertNotFound();
});

it('renders every page with the seeded demo data', function () {
    config(['demo.email' => 'demo@example.com', 'demo.password' => 'password']);
    $this->seed();
    $demo = User::query()->where('is_demo', true)->sole();
    $household = $demo->households()->sole();
    $list = ShoppingList::query()->where('title', 'Weekly groceries')->sole();

    $this->actingAs($demo)->withSession(['auth.password_confirmed_at' => time()]);

    $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('suggestions', fn ($suggestions) => count($suggestions) > 0)
        ->where('suggestions.0.owner.uuid', $household->uuid));
    $this->get(route('lists.show', $list))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('suggestions', fn ($suggestions) => collect($suggestions)->every(fn (array $suggestion): bool => $suggestion['reason'] !== '')));
    $this->get(route('spending.index', ['household' => $household->uuid, 'months' => 6]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('summary.currencies.0.by_category', fn ($categories) => count($categories) >= 5));
    $this->get(route('spending.prices', ['household' => $household->uuid]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('products', fn ($products) => collect($products)->pluck('name')->contains('Coffee')));

    foreach ([
        route('lists.index'),
        route('history.index'),
        route('purchases.index', ['household' => $household->uuid]),
        route('households.show', $household),
        route('staples.index', ['household' => $household->uuid]),
        route('notifications.index'),
        route('organize.index', ['household' => $household->uuid]),
        route('notification-settings.edit'),
    ] as $url) {
        $this->get($url)->assertOk();
    }
});

it('reads the name and taglines from configuration', function () {
    config(['app.name' => 'Basket Co', 'app.tagline' => 'Fill it up.', 'app.tagline_long' => 'A better basket.']);

    $this->get('/')
        ->assertOk()
        ->assertSee('<meta name="application-name" content="Basket Co">', false)
        ->assertSee('<meta name="description" content="A better basket.">', false)
        ->assertInertia(fn (Assert $page) => $page
            ->where('name', 'Basket Co')
            ->where('tagline', 'Fill it up.')
            ->where('taglineLong', 'A better basket.'));
});
