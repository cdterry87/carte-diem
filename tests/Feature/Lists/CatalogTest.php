<?php

use App\Models\Category;
use App\Models\Household;
use App\Models\Store;
use App\Models\User;
use App\Services\ProductResolver;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Names of the categories the organize page offers, for the user's own space or a household.
 *
 * @return array<int, string>
 */
function organizeCategories($test, ?Household $household = null): array
{
    $names = [];
    $test->get(route('organize.index', $household ? ['household' => $household->uuid] : []))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$names) {
            $names = array_column($page->toArray()['props']['categories'], 'name');
        });

    return $names;
}

function customCategory(string $name): Category
{
    return Category::query()->where('scope_type', '!=', 'system')->where('name', $name)->latest('id')->firstOrFail();
}

it('ships the default categories', function () {
    $this->actingAs(User::factory()->create());

    expect(organizeCategories($this))->toHaveCount(11)
        ->and(organizeCategories($this)[0])->toBe('Produce');
});

it('scopes custom categories to their household', function () {
    $household = Household::factory()->create();
    $owner = ['owner' => ['type' => 'household', 'uuid' => $household->uuid]];

    $this->actingAs($household->owner);
    $this->post(route('organize.categories.store'), [...$owner, 'name' => 'Garden'])->assertSessionHasNoErrors();
    $garden = customCategory('Garden');
    $this->post(route('organize.categories.store'), [...$owner, 'name' => 'Garden'])->assertRedirect();
    expectConflictToast();
    expect(organizeCategories($this, $household))->toHaveCount(12);

    $this->actingAs(User::factory()->create());
    $this->get(route('organize.index', ['household' => $household->uuid]))->assertNotFound();
    expect(organizeCategories($this))->toHaveCount(11);
    $this->patch(route('organize.categories.update', $garden), ['name' => 'Hacked'])->assertNotFound();
});

it('protects system categories from edits', function () {
    $this->actingAs(User::factory()->create());
    $category = Category::query()->where('scope_type', 'system')->firstOrFail();

    $this->patch(route('organize.categories.update', $category), ['name' => 'Nope'])->assertForbidden();
    $this->delete(route('organize.categories.destroy', $category))->assertForbidden();
});

it('deactivates custom categories and can bring them back', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('organize.categories.store'), ['owner' => ['type' => 'user'], 'name' => 'Garden']);
    $this->delete(route('organize.categories.destroy', customCategory('Garden')))->assertRedirect();
    expect(organizeCategories($this))->toHaveCount(11);

    $this->post(route('organize.categories.store'), ['owner' => ['type' => 'user'], 'name' => 'Garden'])->assertSessionHasNoErrors();
    expect(organizeCategories($this))->toHaveCount(12);
});

it('manages stores within the owner scope only', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user)->post(route('organize.stores.store'), ['owner' => ['type' => 'user'], 'name' => 'Corner Market'])->assertSessionHasNoErrors();
    $store = Store::query()->sole();
    $this->post(route('organize.stores.store'), ['owner' => ['type' => 'user', 'uuid' => $other->uuid], 'name' => 'X'])->assertNotFound();

    $this->actingAs($other);
    $this->patch(route('organize.stores.update', $store), ['name' => 'Hacked'])->assertNotFound();
    $this->delete(route('organize.stores.destroy', $store))->assertNotFound();
    $this->get(route('organize.index'))->assertInertia(fn (Assert $page) => $page->has('stores', 0));

    expect($store->fresh()->name)->toBe('Corner Market');
});

it('resolves names to one product per owner, inferring category', function () {
    $user = User::factory()->create();
    $resolver = app(ProductResolver::class);

    $first = $resolver->resolve($user, 'Whole  Milk');
    $second = $resolver->resolve($user, 'whole milk');
    $otherOwner = $resolver->resolve(User::factory()->create(), 'whole milk');

    expect($first->id)->toBe($second->id)
        ->and($otherOwner->id)->not->toBe($first->id)
        ->and($first->defaultCategory->slug)->toBe('dairy-eggs');
});

it('favorites products in scope only', function () {
    $user = User::factory()->create();
    $product = app(ProductResolver::class)->resolve($user, 'Whole Milk');

    $this->actingAs($user)->post(route('products.favorite', $product))->assertRedirect();
    expect($product->favoritedBy()->pluck('users.id')->all())->toBe([$user->id]);

    $this->post(route('products.favorite', $product))->assertRedirect();
    expect($product->favoritedBy()->count())->toBe(0);

    $this->actingAs(User::factory()->create())->post(route('products.favorite', $product))->assertNotFound();
});

it('renames a category so its old name becomes available', function () {
    $this->actingAs(User::factory()->create());
    $owner = ['owner' => ['type' => 'user']];

    $this->post(route('organize.categories.store'), [...$owner, 'name' => 'Garden']);
    $garden = customCategory('Garden');
    $this->post(route('organize.categories.store'), [...$owner, 'name' => 'Tools'])->assertSessionHasNoErrors();

    $this->patch(route('organize.categories.update', $garden), ['name' => 'Tools'])->assertRedirect();
    expectConflictToast();
    $this->patch(route('organize.categories.update', $garden), ['name' => 'Yard'])->assertSessionHasNoErrors();
    expect($garden->refresh()->slug)->toBe('yard');
    $this->post(route('organize.categories.store'), [...$owner, 'name' => 'Garden'])->assertSessionHasNoErrors();
    expect(customCategory('Garden')->id)->not->toBe($garden->id);
});

it('can rename a category to the name of a removed one', function () {
    $this->actingAs(User::factory()->create());
    $owner = ['owner' => ['type' => 'user']];

    $this->post(route('organize.categories.store'), [...$owner, 'name' => 'Garden']);
    $this->delete(route('organize.categories.destroy', customCategory('Garden')));
    $this->post(route('organize.categories.store'), [...$owner, 'name' => 'Yard']);
    $yard = customCategory('Yard');

    $this->patch(route('organize.categories.update', $yard), ['name' => 'Garden'])->assertSessionHasNoErrors();
    expect($yard->refresh()->name)->toBe('Garden');
});
