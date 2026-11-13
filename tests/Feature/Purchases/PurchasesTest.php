<?php

use App\Actions\Account\DeleteAccount;
use App\Actions\Trips\CompleteShoppingTrip;
use App\Enums\HouseholdRole;
use App\Enums\ListShareRole;
use App\Enums\VerificationStatus;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Household;
use App\Models\Product;
use App\Models\PurchaseTransaction;
use App\Models\ShoppingList;
use App\Models\ShoppingTrip;
use App\Models\Store;
use App\Models\User;
use App\Services\PurchaseStats;
use Illuminate\Database\Eloquent\Model;
use Inertia\Testing\AssertableInertia as Assert;

function householdWith(User $user, HouseholdRole $role = HouseholdRole::Owner, array $attributes = []): Household
{
    $household = Household::factory()->create($attributes);
    $household->members()->attach($user, ['role' => $role, 'joined_at' => now()]);

    return $household;
}

function ownerRef(Model $owner): array
{
    return ['type' => $owner->getMorphClass(), 'uuid' => $owner->getAttribute('uuid')];
}

function purchasePayload(Model $owner, array $overrides = []): array
{
    return [
        'owner' => ownerRef($owner),
        'merchant' => 'Corner Market',
        'purchased_on' => now()->toDateString(),
        'total_minor' => 2500,
        ...$overrides,
    ];
}

/**
 * The web query that selects an owner's space on purchase and spending pages.
 *
 * @return array<string, string>
 */
function spaceQuery(Model $owner): array
{
    return $owner instanceof Household ? ['household' => $owner->uuid] : [];
}

/**
 * @return array<string, mixed> The purchase as its page shows it.
 */
function purchasePage($test, string $uuid): array
{
    return pageProps($test->get(route('purchases.show', $uuid)), 'purchase');
}

/**
 * Save a purchase through the web form, and return it as its page shows it.
 *
 * @return array<string, mixed>
 */
function postPurchase($test, Model $owner, array $overrides = []): array
{
    $response = $test->post(route('purchases.store'), purchasePayload($owner, $overrides))->assertSessionHasNoErrors()->assertRedirect();

    return purchasePage($test, basename((string) parse_url((string) $response->headers->get('Location'), PHP_URL_PATH)));
}

/**
 * @return array<string, mixed> The spending summary for an owner's space.
 */
function spending($test, ?Model $owner = null, ?int $months = null): array
{
    $query = [...($owner ? spaceQuery($owner) : []), ...($months ? ['months' => $months] : [])];

    return pageProps($test->get(route('spending.index', $query)), 'summary');
}

/**
 * @return mixed The price history page's product list, or one product when asked for.
 */
function priceHistory($test, ?Model $owner = null, ?Product $product = null): mixed
{
    $query = [...($owner ? spaceQuery($owner) : []), ...($product ? ['product' => $product->uuid] : [])];

    return pageProps($test->get(route('spending.prices', $query)), $product ? 'product' : 'products');
}

function activeTrip(Model $owner, ?User $starter = null): ShoppingTrip
{
    $list = ShoppingList::query()->create(['owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey(), 'title' => 'Weekly']);
    $item = $list->items()->create(['name_snapshot' => 'milk', 'sort_order' => 1]);
    $trip = ShoppingTrip::query()->create(['shopping_list_id' => $list->id, 'status' => 'active', 'started_at' => now(), 'started_by' => $starter?->id]);
    $trip->items()->create(['source_list_item_id' => $item->id, 'name_snapshot' => 'milk', 'purchased' => true, 'sort_order' => 1]);

    return $trip;
}

beforeEach(function () {});

describe('standalone purchases', function () {
    it('records a purchase with no trip and feeds suggestions from its lines', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $data = postPurchase($this, $user, [
            'tax_minor' => 100,
            'items' => [['description' => 'Oat milk', 'quantity' => 2, 'line_total_minor' => 900]],
        ]);

        expect($data)->toMatchArray(['merchant' => 'Corner Market', 'total_minor' => 2500, 'tax_minor' => 100, 'entry_method' => 'manual', 'verification_status' => 'verified', 'trip' => null]);
        expect(ShoppingTrip::query()->count())->toBe(0);
        expect(app(PurchaseStats::class)->forOwner($user))->toHaveCount(1);
    });

    it('returns the original purchase when an idempotency key repeats', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $first = postPurchase($this, $user, ['idempotency_key' => 'abc']);

        $this->post(route('purchases.store'), purchasePayload($user, ['idempotency_key' => 'abc', 'total_minor' => 999]))
            ->assertRedirect(route('purchases.show', $first['uuid']));
        expect(purchasePage($this, $first['uuid'])['total_minor'])->toBe(2500);

        expect(PurchaseTransaction::query()->count())->toBe(1);
    });

    it('does not share idempotency keys between users', function () {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->actingAs($first);
        postPurchase($this, $first, ['idempotency_key' => 'abc']);
        $this->actingAs($second);
        postPurchase($this, $second, ['idempotency_key' => 'abc']);

        expect(PurchaseTransaction::query()->count())->toBe(2);
    });

    it('validates amounts and dates', function (array $overrides, string $field) {
        $user = User::factory()->create(['timezone' => 'UTC']);
        $this->actingAs($user);

        $this->post(route('purchases.store'), purchasePayload($user, $overrides))->assertSessionHasErrors($field);
        expect(PurchaseTransaction::query()->count())->toBe(0);
    })->with([
        'no total' => [['total_minor' => null], 'total_minor'],
        'fractional total' => [['total_minor' => 12.5], 'total_minor'],
        'negative total' => [['total_minor' => -1], 'total_minor'],
        'no merchant' => [['merchant' => ''], 'merchant'],
        'future date' => [['purchased_on' => '2999-01-01'], 'purchased_on'],
        'bad date' => [['purchased_on' => '01/02/2026'], 'purchased_on'],
    ]);

    it('accepts today in the user\'s zone even when it is already tomorrow in UTC', function () {
        $this->travelTo('2026-11-05 23:30:00');
        $user = User::factory()->create(['timezone' => 'Asia/Tokyo']);
        $this->actingAs($user);

        $data = postPurchase($this, $user, ['purchased_on' => '2026-11-06']);

        expect($data['purchased_on'])->toBe('2026-11-06');
    });

    it('flags a likely duplicate and allows an override', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        postPurchase($this, $user);

        $this->post(route('purchases.store'), purchasePayload($user))->assertSessionHasErrors('allow_duplicate');
        expect(PurchaseTransaction::query()->count())->toBe(1);

        postPurchase($this, $user, ['allow_duplicate' => true]);
        expect(PurchaseTransaction::query()->count())->toBe(2);
    });

    it('does not treat an equal total alone as a duplicate', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        postPurchase($this, $user);

        postPurchase($this, $user, ['merchant' => 'Other Shop']);
        postPurchase($this, $user, ['purchased_on' => now()->subDays(3)->toDateString()]);

        expect(PurchaseTransaction::query()->count())->toBe(3);
    });

    it('points manual entry at a same-day trip that has no amount yet, then lets the trip take the total', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        $trip = activeTrip($user, $user);
        app(CompleteShoppingTrip::class)->handle($trip, $user);
        $tripPurchase = $trip->purchase()->sole();

        $this->post(route('purchases.store'), purchasePayload($user))->assertSessionHasErrors('allow_duplicate');
        expect(PurchaseTransaction::query()->count())->toBe(1);

        $this->patch(route('purchases.update', $tripPurchase), ['total_minor' => 2500])->assertRedirect(route('purchases.show', $tripPurchase));
        expect($tripPurchase->refresh()->total_minor)->toBe(2500);
    });

});

describe('corrections', function () {
    it('replaces amounts without counting twice and keeps an audit trail', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        $purchase = postPurchase($this, $user);

        $this->patch(route('purchases.update', $purchase['uuid']), ['total_minor' => 3000, 'revision' => 1])->assertSessionHasNoErrors();
        expect(purchasePage($this, $purchase['uuid'])['revision'])->toBe(2);

        expect(spending($this)['currencies'][0])->toMatchArray(['total_minor' => 3000, 'purchase_count' => 1]);

        $log = AuditLog::query()->where('action', 'purchase.corrected')->sole();
        expect($log->meta['previous_amounts'])->toBe(['total_minor' => 2500]);
    });

    it('rejects a stale revision', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        $purchase = postPurchase($this, $user);
        $this->patch(route('purchases.update', $purchase['uuid']), ['total_minor' => 3000, 'revision' => 1])->assertSessionHasNoErrors();

        $this->patch(route('purchases.update', $purchase['uuid']), ['total_minor' => 3500, 'revision' => 1])->assertRedirect();
        expectConflictToast();

        expect(PurchaseTransaction::query()->sole()->total_minor)->toBe(3000);
    });

    it('does not bump the revision when nothing changed', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        $purchase = postPurchase($this, $user, ['items' => [['description' => 'Tea']]]);
        $item = $purchase['items'][0];

        $this->patch(route('purchases.update', $purchase['uuid']), [
            'total_minor' => 2500,
            'items' => [['uuid' => $item['uuid'], 'description' => 'Tea', 'category' => $item['category_uuid']]],
        ])->assertSessionHasNoErrors();
        expect(purchasePage($this, $purchase['uuid'])['revision'])->toBe(1);
    });

    it('reclassifies, renames, and removes lines', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        $purchase = postPurchase($this, $user, ['items' => [['description' => 'Tea'], ['description' => 'Soap']]]);
        [$tea] = $purchase['items'];
        $household = Category::query()->where('scope_type', 'system')->where('slug', 'household')->sole();

        $this->patch(route('purchases.update', $purchase['uuid']), [
            'items' => [['uuid' => $tea['uuid'], 'description' => 'Green tea', 'category' => $household->uuid, 'line_total_minor' => 450]],
        ])->assertSessionHasNoErrors();
        $updated = purchasePage($this, $purchase['uuid'])['items'];

        expect($updated)->toHaveCount(1);
        expect($updated[0])->toMatchArray(['uuid' => $tea['uuid'], 'description' => 'Green tea', 'category' => $household->name, 'line_total_minor' => 450]);
    });

    it('rejects lines and categories from elsewhere', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        $purchase = postPurchase($this, $user, ['items' => [['description' => 'Tea']]]);
        $other = householdWith(User::factory()->create());
        $foreign = Category::query()->create(['name' => 'Theirs', 'slug' => 'theirs', 'scope_type' => 'household', 'scope_id' => $other->id]);

        $this->patch(route('purchases.update', $purchase['uuid']), ['items' => [['uuid' => (string) str()->uuid(), 'description' => 'X']]])
            ->assertSessionHasErrors('items.0.uuid');

        $this->patch(route('purchases.update', $purchase['uuid']), ['items' => [['description' => 'X', 'category' => $foreign->uuid]]])
            ->assertSessionHasErrors('items.0.category');
    });

    it('keeps a needs-review purchase out of spending and suggestions until checked', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        $purchase = postPurchase($this, $user, ['needs_review' => true, 'items' => [['description' => 'Tea']]]);

        expect(app(PurchaseStats::class)->forOwner($user))->toHaveCount(0);
        expect(spending($this))->currencies->toBe([])->needs_review_count->toBe(1);

        $this->patch(route('purchases.update', $purchase['uuid']), ['needs_review' => false])->assertSessionHasNoErrors();

        expect(app(PurchaseStats::class)->forOwner($user))->toHaveCount(1);
        expect(spending($this)['currencies'][0]['total_minor'])->toBe(2500);
    });

    it('removes a deleted purchase from spending and restores it', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        $purchase = postPurchase($this, $user);

        $this->delete(route('purchases.destroy', $purchase['uuid']))->assertRedirect(route('purchases.index'));
        expect(spending($this)['currencies'])->toBe([]);
        $this->get(route('purchases.show', $purchase['uuid']))->assertNotFound();

        $this->post(route('purchases.restore', $purchase['uuid']))->assertRedirect();
        expect(spending($this)['currencies'][0]['total_minor'])->toBe(2500);
    });
});

describe('spending summary', function () {
    it('groups by month in the owner\'s zone and by store, ignoring case', function () {
        $this->travelTo('2026-11-15 12:00:00');
        $user = User::factory()->create();
        $household = householdWith($user, attributes: ['timezone' => 'America/Los_Angeles']);
        $this->actingAs($user);

        foreach ([
            ['Market', 1000, '2026-11-10 12:00:00'],
            ['MARKET', 500, '2026-11-01 03:00:00'], // Still October 31 in Los Angeles.
            ['Bakery', 300, '2026-10-05 12:00:00'],
        ] as $index => [$merchant, $total, $at]) {
            PurchaseTransaction::query()->create([
                'owner_type' => 'household', 'owner_id' => $household->id, 'merchant_name_snapshot' => $merchant, 'total_minor' => $total, 'purchased_at' => $at,
                'entry_method' => 'manual', 'verification_status' => 'verified', 'source_kind' => 'test', 'source_identifier' => (string) $index,
            ]);
        }

        $data = spending($this, $household, 3);

        expect($data['timezone'])->toBe('America/Los_Angeles');
        expect($data['currencies'][0]['total_minor'])->toBe(1800);
        expect($data['currencies'][0]['by_month'])->toBe([
            ['month' => '2026-11', 'total_minor' => 1000, 'purchase_count' => 1],
            ['month' => '2026-10', 'total_minor' => 800, 'purchase_count' => 2],
            ['month' => '2026-09', 'total_minor' => 0, 'purchase_count' => 0],
        ]);
        expect($data['currencies'][0]['by_store'][0])->toMatchArray(['total_minor' => 1500, 'purchase_count' => 2]);
    });

    it('excludes old purchases, counts missing totals, and never adds currencies together', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        foreach ([
            ['USD', 1000, now()],
            ['CAD', 700, now()],
            ['USD', null, now()],
            ['USD', 9999, now()->subMonths(5)],
        ] as $index => [$currency, $total, $at]) {
            PurchaseTransaction::query()->create([
                'owner_type' => 'user', 'owner_id' => $user->id, 'currency_code' => $currency, 'total_minor' => $total, 'purchased_at' => $at,
                'entry_method' => 'manual', 'verification_status' => VerificationStatus::Verified, 'source_kind' => 'test', 'source_identifier' => (string) $index,
            ]);
        }

        $data = spending($this, months: 3);

        expect(collect($data['currencies'])->pluck('total_minor', 'currency_code')->all())->toBe(['USD' => 1000, 'CAD' => 700]);
        expect($data['missing_total_count'])->toBe(1);
    });

    it('breaks spending down by category with coverage and monthly trends', function () {
        $this->travelTo('2026-11-15 12:00:00');
        $user = User::factory()->create();
        $this->actingAs($user);
        $produce = Category::query()->where('name', 'Produce')->sole();
        $dairy = Category::query()->where('name', 'Dairy & Eggs')->sole();

        postPurchase($this, $user, ['purchased_on' => '2026-11-10', 'total_minor' => 1000, 'items' => [
            ['description' => 'Apples', 'line_total_minor' => 400, 'category' => $produce->uuid],
            ['description' => 'Pears', 'line_total_minor' => 100, 'category' => $produce->uuid],
            ['description' => 'Milk', 'line_total_minor' => 300, 'category' => $dairy->uuid],
            ['description' => 'Unpriced', 'category' => $dairy->uuid],
        ]]);
        postPurchase($this, $user, ['purchased_on' => '2026-10-10', 'total_minor' => 700, 'merchant' => 'Other', 'items' => [
            ['description' => 'Apples', 'line_total_minor' => 600, 'category' => $produce->uuid],
        ]]);
        // Not counted in totals, so not counted in categories either.
        $noTotal = postPurchase($this, $user, ['purchased_on' => '2026-11-11', 'merchant' => 'No total', 'items' => [
            ['description' => 'Apples', 'line_total_minor' => 999, 'category' => $produce->uuid],
        ]]);
        PurchaseTransaction::query()->where('uuid', $noTotal['uuid'])->update(['total_minor' => null]);

        $usd = spending($this, months: 3)['currencies'][0];

        expect($usd['total_minor'])->toBe(1700);
        expect($usd['itemized_minor'])->toBe(1400);
        expect($usd['by_category'][0])->toMatchArray(['name' => 'Produce', 'total_minor' => 1100, 'purchase_count' => 2]);
        expect($usd['by_category'][1])->toMatchArray(['name' => 'Dairy & Eggs', 'total_minor' => 300, 'purchase_count' => 1]);
        expect($usd['by_category'][0]['by_month'])->toBe([
            ['month' => '2026-11', 'total_minor' => 500, 'purchase_count' => 1],
            ['month' => '2026-10', 'total_minor' => 600, 'purchase_count' => 1],
            ['month' => '2026-09', 'total_minor' => 0, 'purchase_count' => 0],
        ]);
    });

    it('compares the last complete month, even when the period does not reach that far', function () {
        $this->travelTo('2026-11-15 12:00:00');
        $user = User::factory()->create();
        $this->actingAs($user);

        foreach ([['2026-11-02', 5000], ['2026-10-10', 1200], ['2026-09-10', 1000], ['2025-10-10', 1500], ['2025-09-10', 8888]] as [$on, $total]) {
            postPurchase($this, $user, ['purchased_on' => $on, 'total_minor' => $total, 'merchant' => $on]);
        }

        $usd = spending($this, months: 3)['currencies'][0];

        expect($usd['comparison'])->toBe(['month' => '2026-10', 'total_minor' => 1200, 'previous_month_minor' => 1000, 'last_year_minor' => 1500]);
        expect($usd['total_minor'])->toBe(7200);
    });

    it('has nothing to compare without a complete month, and treats empty months as unknown', function () {
        $this->travelTo('2026-11-15 12:00:00');
        $user = User::factory()->create();
        $this->actingAs($user);

        postPurchase($this, $user, ['purchased_on' => '2026-11-02']);
        expect(spending($this)['currencies'][0]['comparison'])->toBeNull();

        postPurchase($this, $user, ['purchased_on' => '2026-10-02', 'total_minor' => 900]);
        expect(spending($this)['currencies'][0]['comparison'])
            ->toBe(['month' => '2026-10', 'total_minor' => 900, 'previous_month_minor' => null, 'last_year_minor' => null]);
    });

    it('rejects an unsupported period', function () {
        $this->actingAs(User::factory()->create());

        $this->get(route('spending.index', ['months' => 7]))->assertSessionHasErrors('months');
    });
});

describe('access', function () {
    it('hides purchases and spending from outsiders', function () {
        $member = User::factory()->create();
        $household = householdWith($member);
        $this->actingAs($member);
        $purchase = postPurchase($this, $household);

        $this->actingAs(User::factory()->create());

        $this->get(route('purchases.show', $purchase['uuid']))->assertNotFound();
        $this->patch(route('purchases.update', $purchase['uuid']), ['total_minor' => 1])->assertNotFound();
        $this->delete(route('purchases.destroy', $purchase['uuid']))->assertNotFound();
        $this->get(route('spending.index', spaceQuery($household)))->assertNotFound();
        $this->post(route('purchases.store'), purchasePayload($household))->assertNotFound();
    });

    it('gives list guests no spending, even while running a trip', function () {
        $member = User::factory()->create();
        $household = householdWith($member);
        $guest = User::factory()->create();
        $trip = activeTrip($household, $member);
        $trip->shoppingList->shares()->create(['user_id' => $guest->id, 'role' => ListShareRole::Editor]);

        $this->actingAs($guest);
        $this->get(route('spending.index', spaceQuery($household)))->assertNotFound();
    });

    it('lets members correct a purchase but only its creator or a manager delete it', function () {
        $creator = User::factory()->create();
        $household = householdWith($creator, HouseholdRole::Member);
        $member = User::factory()->create();
        $admin = User::factory()->create();
        $household->members()->attach($member, ['role' => HouseholdRole::Member, 'joined_at' => now()]);
        $household->members()->attach($admin, ['role' => HouseholdRole::Admin, 'joined_at' => now()]);

        $this->actingAs($creator);
        $purchase = postPurchase($this, $household);

        $this->actingAs($member);
        $this->patch(route('purchases.update', $purchase['uuid']), ['total_minor' => 2600])->assertSessionHasNoErrors();
        $this->delete(route('purchases.destroy', $purchase['uuid']))->assertForbidden();

        $this->actingAs($admin);
        $this->delete(route('purchases.destroy', $purchase['uuid']))->assertRedirect();

        $this->actingAs($member);
        $this->post(route('purchases.restore', $purchase['uuid']))->assertForbidden();
    });

});

function pricedLine(string $description, int $total, ?string $quantity = null, ?string $unit = null): array
{
    return ['description' => $description, 'line_total_minor' => $total, 'quantity' => $quantity, 'unit' => $unit];
}

describe('price history', function () {
    it('lists products with two prices in a unit, oldest to latest', function () {
        $this->travelTo('2026-11-15 12:00:00');
        $user = User::factory()->create();
        $this->actingAs($user);

        postPurchase($this, $user, ['purchased_on' => '2026-11-01', 'merchant' => 'B', 'items' => [pricedLine('Coffee', 1100), pricedLine('Once', 100)]]);
        postPurchase($this, $user, ['purchased_on' => '2026-09-01', 'merchant' => 'A', 'items' => [pricedLine('Coffee', 1000)]]);

        $products = priceHistory($this);

        expect($products)->toHaveCount(1);
        expect($products[0])->toMatchArray(['name' => 'Coffee', 'unit' => 'each', 'purchase_count' => 2, 'first_minor' => 1000, 'latest_minor' => 1100]);
    });

    it('compares per unit, converting only where that is safe', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        postPurchase($this, $user, ['merchant' => 'A', 'items' => [pricedLine('Rice', 1000, '2', 'kg'), pricedLine('Eggs', 600, '1', 'dozen'), pricedLine('Beans', 300, '12', 'oz')]]);
        postPurchase($this, $user, ['merchant' => 'B', 'purchased_on' => now()->subDay()->toDateString(), 'items' => [pricedLine('Rice', 300, '500', 'g'), pricedLine('Eggs', 300, '6'), pricedLine('Beans', 400, '1', 'lb')]]);

        $byName = collect(priceHistory($this))->groupBy('name');

        expect($byName['Rice'][0])->toMatchArray(['unit' => 'kg', 'purchase_count' => 2, 'first_minor' => 600, 'latest_minor' => 500]);
        expect($byName['Eggs'][0])->toMatchArray(['unit' => 'each', 'purchase_count' => 2, 'first_minor' => 50, 'latest_minor' => 50]);
        expect($byName->has('Beans'))->toBeFalse();

        $beans = Product::query()->where('canonical_name', 'Beans')->sole();
        $series = priceHistory($this, product: $beans)['series'];
        expect(collect($series)->pluck('unit')->sort()->values()->all())->toBe(['lb', 'oz']);
    });

    it('compares stores for a product, lowest average first, ignoring case', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        foreach ([['Dear', 500, 3], ['DEAR', 700, 2], ['Cheap', 400, 1]] as [$merchant, $total, $daysAgo]) {
            postPurchase($this, $user, ['merchant' => $merchant, 'purchased_on' => now()->subDays($daysAgo)->toDateString(), 'items' => [pricedLine('Tea', $total)]]);
        }

        $tea = Product::query()->where('canonical_name', 'Tea')->sole();
        $stores = priceHistory($this, product: $tea)['series'][0]['stores'];

        expect($stores)->toHaveCount(2);
        expect($stores[0])->toMatchArray(['name' => 'Cheap', 'average_minor' => 400, 'purchase_count' => 1]);
        expect($stores[1])->toMatchArray(['average_minor' => 600, 'latest_minor' => 700, 'purchase_count' => 2]);
    });

    it('keeps currencies apart and leaves out unconfirmed, deleted, and old purchases', function () {
        $this->travelTo('2026-11-15 12:00:00');
        $user = User::factory()->create();
        $this->actingAs($user);

        postPurchase($this, $user, ['merchant' => 'A', 'items' => [pricedLine('Tea', 100)]]);
        $canadian = postPurchase($this, $user, ['merchant' => 'B', 'items' => [pricedLine('Tea', 900)]]);
        PurchaseTransaction::query()->where('uuid', $canadian['uuid'])->update(['currency_code' => 'CAD']);
        postPurchase($this, $user, ['merchant' => 'C', 'needs_review' => true, 'items' => [pricedLine('Tea', 200)]]);
        $deleted = postPurchase($this, $user, ['merchant' => 'D', 'items' => [pricedLine('Tea', 300)]]);
        $this->delete(route('purchases.destroy', $deleted['uuid']))->assertRedirect();
        postPurchase($this, $user, ['merchant' => 'E', 'purchased_on' => '2024-01-01', 'items' => [pricedLine('Tea', 400)]]);

        expect(priceHistory($this))->toBe([]);

        $tea = Product::query()->where('canonical_name', 'Tea')->sole();
        $series = priceHistory($this, product: $tea)['series'];
        expect(collect($series)->map(fn (array $one): array => [$one['currency_code'], count($one['prices'])])->sort()->values()->all())
            ->toBe([['CAD', 1], ['USD', 1]]);
    });

    it('hides prices from outsiders and list guests, and products from other spaces', function () {
        $user = User::factory()->create();
        $household = householdWith($user);
        $this->actingAs($user);
        postPurchase($this, $household, ['items' => [pricedLine('Tea', 100)]]);
        $tea = Product::query()->where('canonical_name', 'Tea')->sole();
        $scope = spaceQuery($household);

        $this->get(route('spending.prices', [...$scope, 'product' => $tea->uuid]))->assertOk();
        $this->get(route('spending.prices', ['product' => $tea->uuid]))->assertNotFound();

        $outsider = User::factory()->create();
        $this->actingAs($outsider);
        $this->get(route('spending.prices', $scope))->assertNotFound();
        $this->get(route('spending.prices', ['product' => $tea->uuid]))->assertNotFound();

        $guest = User::factory()->create();
        $list = ShoppingList::query()->create(['owner_type' => 'household', 'owner_id' => $household->id, 'title' => 'Shared']);
        $list->shares()->create(['user_id' => $guest->id, 'role' => ListShareRole::Editor]);
        $this->actingAs($guest);
        $this->get(route('spending.prices', $scope))->assertNotFound();
    });

    it('renders the list and a product, and 404s on a product from elsewhere', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        postPurchase($this, $user, ['merchant' => 'A', 'items' => [pricedLine('Tea', 100)]]);
        postPurchase($this, $user, ['merchant' => 'B', 'purchased_on' => now()->subDay()->toDateString(), 'items' => [pricedLine('Tea', 200)]]);
        $tea = Product::query()->where('canonical_name', 'Tea')->sole();

        $this->get('/spending/prices')->assertInertia(fn (Assert $page) => $page
            ->component('spending/prices')
            ->has('products', 1)
            ->where('product', null));
        $this->get("/spending/prices?product={$tea->uuid}")->assertInertia(fn (Assert $page) => $page
            ->where('product.name', 'Tea')
            ->has('product.series.0.prices', 2));

        $this->actingAs(User::factory()->create(), 'web');
        $this->get("/spending/prices?product={$tea->uuid}")->assertNotFound();
    });
});

describe('web pages', function () {
    it('renders purchases, spending, and the forms for the selected space', function () {
        $user = User::factory()->create();
        $household = householdWith($user);
        $this->actingAs($user);
        $purchase = postPurchase($this, $household, ['items' => [['description' => 'Tea']]]);

        $this->get("/purchases?household={$household->uuid}")->assertInertia(fn (Assert $page) => $page
            ->component('purchases/index')
            ->has('purchases', 1));
        $this->get('/purchases')->assertInertia(fn (Assert $page) => $page->has('purchases', 0));
        $this->get("/purchases/create?household={$household->uuid}")->assertInertia(fn (Assert $page) => $page
            ->component('purchases/create')
            ->where('currency', 'USD'));
        $this->get("/purchases/{$purchase['uuid']}")->assertInertia(fn (Assert $page) => $page->component('purchases/show')->has('purchase.items', 1));
        $this->get("/purchases/{$purchase['uuid']}/edit")->assertInertia(fn (Assert $page) => $page->component('purchases/edit')->has('categories'));
        $this->get("/spending?household={$household->uuid}&months=6")->assertInertia(fn (Assert $page) => $page
            ->component('spending/index')
            ->where('summary.months', 6)
            ->where('summary.currencies.0.total_minor', 2500));
    });

    it('saves a purchase, and shows duplicates as a form error', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post('/purchases', purchasePayload($user))->assertRedirect();

        $this->from('/purchases/create')->post('/purchases', purchasePayload($user))
            ->assertRedirect('/purchases/create')
            ->assertSessionHasErrors('allow_duplicate');
    });

});

describe('amounts during a trip', function () {
    it('carries a total and item prices onto the purchase when the trip completes', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        $trip = activeTrip($user, $user);
        $item = $trip->items()->sole();

        $this->patch(route('trips.items.update', [$trip, $item->uuid]), ['line_total_minor' => 450])->assertSessionHasNoErrors();
        $this->patch(route('trips.amount', $trip), ['total_minor' => 500])->assertSessionHasNoErrors();
        expect(pageProps($this->get(route('trips.show', $trip)), 'trip'))
            ->total_minor->toBe(500)
            ->items->{0}->line_total_minor->toBe(450);

        app(CompleteShoppingTrip::class)->handle($trip, $user);

        $purchase = PurchaseTransaction::query()->sole();
        expect($purchase->total_minor)->toBe(500);
        expect($purchase->items()->sole()->line_total_minor)->toBe(450);
        expect(spending($this)['currencies'][0]['total_minor'])->toBe(500);
    });

    it('never reaches spending from a cancelled trip, and rejects changes after a trip ends', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        $trip = activeTrip($user, $user);
        $this->patch(route('trips.amount', $trip), ['total_minor' => 500])->assertSessionHasNoErrors();

        $this->post(route('trips.cancel', $trip))->assertRedirect();

        expect(PurchaseTransaction::query()->count())->toBe(0);
        expect(spending($this)['currencies'])->toBe([]);
        $this->patch(route('trips.amount', $trip), ['total_minor' => 600])->assertRedirect();
        expectConflictToast();
        expect($trip->refresh()->total_minor)->toBe(500);
    });

    it('validates amounts as whole minor units', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        $trip = activeTrip($user, $user);

        $this->patch(route('trips.amount', $trip), ['total_minor' => 1.5])->assertSessionHasErrors('total_minor');
        $this->patch(route('trips.amount', $trip), ['total_minor' => -1])->assertSessionHasErrors('total_minor');
    });

    it('hides amounts from a guest running the trip and stops them setting any', function () {
        $member = User::factory()->create();
        $household = householdWith($member);
        $guest = User::factory()->create();
        $trip = activeTrip($household, $member);
        $trip->update(['total_minor' => 500]);
        $item = $trip->items()->sole();
        $item->update(['line_total_minor' => 450]);
        $trip->shoppingList->shares()->create(['user_id' => $guest->id, 'role' => ListShareRole::Editor]);

        $this->actingAs($guest);
        $this->get(route('trips.show', $trip))->assertInertia(fn (Assert $page) => $page
            ->missing('trip.total_minor')
            ->missing('trip.currency_code')
            ->missing('trip.items.0.line_total_minor')
            ->where('trip.can.manage_amounts', false));

        $this->patch(route('trips.amount', $trip), ['total_minor' => 1])->assertForbidden();
        $this->patch(route('trips.items.update', [$trip, $item->uuid]), ['line_total_minor' => 1])->assertForbidden();
        $this->patch(route('trips.items.update', [$trip, $item->uuid]), ['purchased' => false])->assertRedirect();

        expect($item->fresh())->line_total_minor->toBe(450)->purchased->toBeFalse();
    });

    it('still records the amounts entered by members when a guest completes the trip', function () {
        $member = User::factory()->create();
        $household = householdWith($member);
        $guest = User::factory()->create();
        $trip = activeTrip($household, $member);
        $trip->update(['total_minor' => 500]);
        $trip->shoppingList->shares()->create(['user_id' => $guest->id, 'role' => ListShareRole::Editor]);

        $this->actingAs($guest);
        $this->post(route('trips.complete', $trip))->assertRedirect();
        $this->get(route('trips.show', $trip))->assertInertia(fn (Assert $page) => $page->missing('trip.total_minor'));

        expect(PurchaseTransaction::query()->sole()->total_minor)->toBe(500);
    });
});

describe('moving a purchase', function () {
    it('moves a purchase, and re-matches what is scoped', function () {
        $user = User::factory()->create();
        $household = householdWith($user);
        $this->actingAs($user);
        $store = Store::query()->create(['scope_type' => 'household', 'scope_id' => $household->id, 'name' => 'Corner Market']);
        $custom = Category::query()->create(['name' => 'Mine', 'slug' => 'mine', 'scope_type' => 'user', 'scope_id' => $user->id]);
        $system = Category::query()->where('scope_type', 'system')->where('slug', 'household')->sole();
        $purchase = postPurchase($this, $user, ['items' => [
            ['description' => 'Tea', 'category' => $custom->uuid],
            ['description' => 'Soap', 'category' => $system->uuid],
        ]]);

        $this->post(route('purchases.move', $purchase['uuid']), ['owner' => ownerRef($household), 'revision' => 1])->assertSessionHasNoErrors();
        $moved = purchasePage($this, $purchase['uuid']);
        expect($moved)->owner->uuid->toBe($household->uuid)->revision->toBe(2);

        $model = PurchaseTransaction::query()->sole();
        expect($model->store_id)->toBe($store->id);
        expect($moved['items'][0]['category'])->not->toBe('Mine');
        expect($moved['items'][1]['category'])->toBe($system->name);
        expect(Product::query()->forOwner($household)->pluck('normalized_name')->sort()->values()->all())->toBe(['soap', 'tea']);

        expect(spending($this)['currencies'])->toBe([]);
        expect(spending($this, $household)['currencies'][0]['total_minor'])->toBe(2500);
        expect(app(PurchaseStats::class)->forOwner($household))->toHaveCount(2);
        expect(app(PurchaseStats::class)->forOwner($user))->toHaveCount(0);
    });

    it('cuts off access for people in the old space', function () {
        $owner = User::factory()->create();
        $household = householdWith($owner);
        $member = User::factory()->create();
        $household->members()->attach($member, ['role' => HouseholdRole::Member, 'joined_at' => now()]);
        $this->actingAs($owner);
        $purchase = postPurchase($this, $household);

        $this->post(route('purchases.move', $purchase['uuid']), ['owner' => ownerRef($owner)])->assertSessionHasNoErrors();

        $this->actingAs($member);
        $this->get(route('purchases.show', $purchase['uuid']))->assertNotFound();
    });

    it('takes the creator or a manager, and a space the user belongs to', function () {
        $creator = User::factory()->create();
        $household = householdWith($creator, HouseholdRole::Member);
        $member = User::factory()->create();
        $household->members()->attach($member, ['role' => HouseholdRole::Member, 'joined_at' => now()]);
        $this->actingAs($creator);
        $purchase = postPurchase($this, $household);
        $elsewhere = householdWith(User::factory()->create());

        $this->post(route('purchases.move', $purchase['uuid']), ['owner' => ownerRef($elsewhere)])->assertNotFound();

        $this->actingAs($member);
        $this->post(route('purchases.move', $purchase['uuid']), ['owner' => ownerRef($member)])->assertForbidden();

        expect(PurchaseTransaction::query()->sole()->owner_type)->toBe('household');
    });

    it('refuses a trip purchase, flags duplicates in the new space, and rejects a stale revision', function () {
        $user = User::factory()->create();
        $household = householdWith($user);
        $this->actingAs($user);
        $trip = activeTrip($user, $user);
        app(CompleteShoppingTrip::class)->handle($trip, $user);
        $tripPurchase = $trip->purchase()->sole();

        $this->post(route('purchases.move', $tripPurchase), ['owner' => ownerRef($household)])->assertRedirect();
        expectConflictToast();
        expect($tripPurchase->refresh()->owner_type)->toBe('user');

        $purchase = postPurchase($this, $user, ['merchant' => 'Bakery', 'allow_duplicate' => true]);
        postPurchase($this, $household, ['merchant' => 'Bakery']);
        $moved = fn (): string => PurchaseTransaction::query()->where('uuid', $purchase['uuid'])->sole()->owner_type;

        $this->post(route('purchases.move', $purchase['uuid']), ['owner' => ownerRef($household), 'revision' => 9])->assertRedirect();
        expectConflictToast();
        $this->post(route('purchases.move', $purchase['uuid']), ['owner' => ownerRef($household)])->assertSessionHasErrors('allow_duplicate');
        expect($moved())->toBe('user');
        $this->post(route('purchases.move', $purchase['uuid']), ['owner' => ownerRef($household), 'allow_duplicate' => true])->assertSessionHasNoErrors();
        expect($moved())->toBe('household');
    });
});

it('reports the revision on a purchase just created, so it can be corrected right away', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $purchase = postPurchase($this, $user);

    expect($purchase['revision'])->toBe(1);

    $this->patch(route('purchases.update', $purchase['uuid']), ['total_minor' => 3000, 'revision' => $purchase['revision']])->assertSessionHasNoErrors();
    expect(PurchaseTransaction::query()->sole()->total_minor)->toBe(3000);
});

it('keeps household amounts when the member who entered them deletes their account', function () {
    $owner = User::factory()->create();
    $household = householdWith($owner);
    $leaver = User::factory()->create();
    $household->members()->attach($leaver, ['role' => HouseholdRole::Member, 'joined_at' => now()]);
    $this->actingAs($leaver);
    $purchase = postPurchase($this, $household);

    app(DeleteAccount::class)->handle($leaver);

    expect(PurchaseTransaction::query()->where('uuid', $purchase['uuid'])->sole()->total_minor)->toBe(2500);
});
