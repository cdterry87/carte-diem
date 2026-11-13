<?php

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\Product;
use App\Models\PurchaseTransaction;
use App\Models\ShoppingList;
use App\Models\StapleRule;
use App\Models\User;
use App\Services\ProductResolver;
use App\Services\PurchaseStats;
use App\Services\Recommendations;
use App\Services\StapleSchedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<int, string>  $names
 */
function bought(Model $owner, array $names, int $daysAgo, string $status = 'verified'): PurchaseTransaction
{
    static $sequence = 0;
    $sequence++;

    $purchase = PurchaseTransaction::query()->create([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
        'purchased_at' => now()->subDays($daysAgo),
        'entry_method' => 'trip',
        'verification_status' => $status,
        'source_kind' => 'test',
        'source_identifier' => (string) $sequence,
    ]);

    foreach ($names as $name) {
        $purchase->items()->create([
            'product_id' => app(ProductResolver::class)->resolve($owner, $name)->id,
            'description_snapshot' => $name,
            'is_verified' => true,
        ]);
    }

    return $purchase;
}

function recommend(Model $owner, User $user, ?ShoppingList $list = null): Collection
{
    return app(Recommendations::class)->forOwner($owner, $user, $list)
        ->keyBy(fn ($recommendation) => $recommendation->product->canonical_name);
}

function needOn(Model $owner, string $name, string $status = 'active'): ShoppingList
{
    $list = ShoppingList::query()->create(['owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey(), 'title' => 'L', 'status' => $status]);
    $list->items()->create(['name_snapshot' => $name, 'product_id' => app(ProductResolver::class)->resolve($owner, $name)->id]);

    return $list;
}

it('predicts replenishment from the median interval and explains why', function () {
    $user = User::factory()->create();
    foreach ([28, 21, 14, 7] as $days) {
        bought($user, ['milk'], $days);
    }

    $recommendation = recommend($user, $user)->get('milk');

    expect($recommendation->kind)->toBe('replenish')
        ->and($recommendation->reason)->toBe('You usually buy this every 7 days. Last bought 7 days ago.');
});

it('needs at least three purchases before predicting an interval', function () {
    $user = User::factory()->create();
    bought($user, ['milk'], 40);
    bought($user, ['milk'], 20);

    expect(recommend($user, $user)->get('milk')?->kind)->not->toBe('replenish');
});

it('does not predict before the interval has passed', function () {
    $user = User::factory()->create();
    foreach ([60, 40, 20, 1] as $days) {
        bought($user, ['rice'], $days);
        bought($user, ['other'.$days], $days);
    }

    expect(recommend($user, $user)->get('rice')?->kind)->toBe('frequent');
});

it('reports frequency across recent trips', function () {
    $user = User::factory()->create();
    bought($user, ['eggs'], 3);
    bought($user, ['eggs'], 2);
    bought($user, ['bread'], 1);

    expect(recommend($user, $user)->get('eggs')->reason)->toBe('Purchased on 2 of your last 3 trips.')
        ->and(recommend($user, $user)->has('bread'))->toBeFalse();
});

it('ignores unverified, deleted, and other-scope purchases', function () {
    $user = User::factory()->create();
    bought($user, ['milk'], 3, 'draft');
    bought($user, ['milk'], 2, 'needs_review');
    bought($user, ['milk'], 1)->delete();
    bought(User::factory()->create(), ['milk'], 1);
    bought(User::factory()->create(), ['milk'], 2);

    expect(recommend($user, $user)->get('milk')?->kind)->not->toBeIn(['frequent', 'replenish']);
});

it('does not treat merely listed items as purchases', function () {
    $user = User::factory()->create();
    $list = needOn($user, 'milk');
    $list->items()->update(['status' => 'satisfied']);

    expect(recommend($user, $user)->get('milk')?->kind)->toBe('recent');
});

it('suppresses products already needed on an active list', function () {
    $user = User::factory()->create();
    bought($user, ['eggs'], 3);
    bought($user, ['eggs'], 2);
    needOn($user, 'eggs');

    expect(recommend($user, $user)->has('eggs'))->toBeFalse();
});

it('still suggests products that are only on archived lists', function () {
    $user = User::factory()->create();
    bought($user, ['eggs'], 3);
    bought($user, ['eggs'], 2);
    needOn($user, 'eggs', 'archived');

    expect(recommend($user, $user)->has('eggs'))->toBeTrue();
});

it('suggests items repeatedly bought together with the list', function () {
    $user = User::factory()->create();
    foreach ([100, 50, 1] as $days) {
        bought($user, ['pasta', 'sauce'], $days);
    }
    foreach (range(1, 8) as $index) {
        bought($user, ['filler'.$index], 0);
    }

    $list = needOn($user, 'pasta');

    expect(recommend($user, $user, $list)->get('sauce')->reason)->toBe('Often bought with pasta.')
        ->and(recommend($user, $user)->get('sauce')?->kind)->not->toBe('co_purchase');
});

it('keeps co-purchase off with too few shared trips', function () {
    $user = User::factory()->create();
    bought($user, ['pasta', 'sauce'], 300);
    bought($user, ['pasta', 'sauce'], 200);
    foreach ([9, 8, 7, 6, 5, 4, 3] as $days) {
        bought($user, ['filler'.$days], $days);
    }

    expect(recommend($user, $user, needOn($user, 'pasta'))->has('sauce'))->toBeFalse();
});

it('falls back to recent items with no history, never inventing personalization', function () {
    $user = User::factory()->create();
    app(ProductResolver::class)->resolve($user, 'Tea');

    $recommendation = recommend($user, $user)->get('Tea');

    expect($recommendation->kind)->toBe('recent')->and($recommendation->reason)->toBe('You added this recently.');
    expect(recommend(User::factory()->create(), $user))->toHaveCount(0);
});

it('suggests staples once their cadence passes', function () {
    $user = User::factory()->create();
    $product = app(ProductResolver::class)->resolve($user, 'Coffee');
    bought($user, ['coffee'], 10);
    $rule = StapleRule::query()->create(['owner_type' => 'user', 'owner_id' => $user->id, 'product_id' => $product->id, 'cadence_days' => 7]);

    expect(recommend($user, $user)->get('Coffee')->reason)->toBe('On your staples, every 7 days.');

    $rule->update(['cadence_days' => 30]);
    expect(recommend($user, $user)->get('Coffee')?->kind)->not->toBe('staple');

    $rule->update(['cadence_days' => 7, 'snoozed_until' => now()->addDay()]);
    expect(recommend($user, $user)->has('Coffee'))->toBeFalse();
});

it('waits for the longer of cadence and predicted interval', function () {
    $user = User::factory()->create();
    foreach ([60, 40, 20] as $days) {
        bought($user, ['tea'], $days);
    }
    bought($user, ['other'], 1);
    $product = Product::query()->where('canonical_name', 'tea')->sole();
    $rule = StapleRule::query()->create(['owner_type' => 'user', 'owner_id' => $user->id, 'product_id' => $product->id, 'cadence_days' => 30]);

    expect(app(StapleSchedule::class)->isDue($rule, app(PurchaseStats::class)->forOwner($user)->get($product->id)))->toBeFalse();

    $rule->update(['cadence_days' => 10]);
    expect(app(StapleSchedule::class)->isDue($rule->refresh(), app(PurchaseStats::class)->forOwner($user)->get($product->id)))->toBeTrue();

    $rule->update(['cadence_days' => null, 'prediction_enabled' => false]);
    expect(app(StapleSchedule::class)->isDue($rule->refresh(), app(PurchaseStats::class)->forOwner($user)->get($product->id)))->toBeFalse();
});

it('honors per-user dismiss, snooze, and mute', function (string $action) {
    $household = Household::factory()->create();
    $other = User::factory()->create();
    $household->addMember($other, HouseholdRole::Member);
    bought($household, ['eggs'], 3);
    bought($household, ['eggs'], 2);
    $product = Product::query()->sole();

    $this->actingAs($household->owner)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('suggestions.0.product', $product->uuid)
        ->missing('suggestions.0.product_id'));
    $this->post(route('suggestions.dismiss', $product), ['action' => $action])->assertRedirect();
    expect(recommend($household, $household->owner))->toHaveCount(0)
        ->and(recommend($household, $other))->toHaveCount(1);

    $this->delete(route('suggestions.lift', $product))->assertRedirect();
    expect(recommend($household, $household->owner))->toHaveCount(1);
})->with(['dismiss', 'snooze', 'mute']);

it('brings dismissed suggestions back after the window but keeps mutes', function () {
    $user = User::factory()->create();
    bought($user, ['eggs'], 3);
    bought($user, ['eggs'], 2);
    $this->actingAs($user);
    $product = Product::query()->sole();

    $this->post(route('suggestions.dismiss', $product), ['action' => 'snooze'])->assertRedirect();
    $this->travel(8)->days();
    expect(recommend($user, $user)->has('eggs'))->toBeTrue();

    $this->post(route('suggestions.dismiss', $product), ['action' => 'mute'])->assertRedirect();
    $this->travel(400)->days();
    expect(recommend($user, $user)->has('eggs'))->toBeFalse();
});

it('keeps suggestions and staples inside the owner scope', function () {
    $household = Household::factory()->create();
    bought($household, ['eggs'], 3);
    bought($household, ['eggs'], 2);
    $product = Product::query()->sole();
    $rule = StapleRule::query()->create(['owner_type' => 'household', 'owner_id' => $household->id, 'product_id' => $product->id]);
    $owner = ['owner' => ['type' => 'household', 'uuid' => $household->uuid]];

    $this->actingAs(User::factory()->create());
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('suggestions', []));
    $this->get(route('staples.index', ['household' => $household->uuid]))->assertNotFound();
    $this->post(route('staples.store'), [...$owner, 'name' => 'x'])->assertNotFound();
    $this->post(route('suggestions.dismiss', $product), ['action' => 'mute'])->assertNotFound();
    $this->patch(route('staples.update', $rule), ['cadence_days' => 1])->assertNotFound();
    $this->delete(route('staples.destroy', $rule))->assertNotFound();
});

it('never gives list guests household recommendations', function () {
    $household = Household::factory()->create();
    bought($household, ['secret medicine'], 3);
    bought($household, ['secret medicine'], 2);
    $list = needOn($household, 'milk');
    $guest = User::factory()->create();
    $list->shares()->create(['user_id' => $guest->id, 'role' => 'editor']);

    $this->actingAs($guest);

    expect(collect($this->getJson(route('lists.suggestions', $list))->assertOk()->json('data'))->pluck('name'))->not->toContain('secret medicine');
});

it('explains inline suggestions for scope members', function () {
    $user = User::factory()->create();
    bought($user, ['eggs'], 3);
    bought($user, ['eggs'], 2);
    $list = ShoppingList::query()->create(['owner_type' => 'user', 'owner_id' => $user->id, 'title' => 'L']);
    $this->actingAs($user);

    $this->getJson(route('lists.suggestions', $list))
        ->assertJsonPath('data.0.name', 'eggs')
        ->assertJsonPath('data.0.reason', 'Purchased on 2 of your last 2 trips.');
});

it('manages staples', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post(route('staples.store'), ['owner' => ['type' => 'user'], 'name' => 'Coffee', 'cadence_days' => 14])->assertSessionHasNoErrors();
    $rule = StapleRule::query()->sole();
    expect($rule->product->canonical_name)->toBe('Coffee')
        ->and($rule->prediction_enabled)->toBeTrue();

    $this->post(route('staples.store'), ['owner' => ['type' => 'user'], 'name' => 'coffee'])->assertRedirect();
    expectConflictToast();
    $this->post(route('staples.store'), ['owner' => ['type' => 'user'], 'name' => 'x', 'cadence_days' => 0])->assertSessionHasErrors('cadence_days');

    $this->patch(route('staples.update', $rule), ['reminder_enabled' => false, 'snooze_days' => 3])->assertSessionHasNoErrors();
    expect($rule->refresh()->reminder_enabled)->toBeFalse()
        ->and($rule->isSnoozed())->toBeTrue();

    $this->patch(route('staples.update', $rule), ['snooze_days' => null])->assertSessionHasNoErrors();
    expect($rule->refresh()->snoozed_until)->toBeNull();
    $this->delete(route('staples.destroy', $rule))->assertRedirect();
    expect(StapleRule::query()->count())->toBe(0);
});
