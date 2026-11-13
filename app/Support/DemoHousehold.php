<?php

namespace App\Support;

use App\Actions\Lists\AddListItem;
use App\Actions\Lists\CreateShoppingList;
use App\Actions\Lists\SaveCategory;
use App\Enums\EntryMethod;
use App\Enums\ListItemStatus;
use App\Enums\ListStatus;
use App\Enums\TripStatus;
use App\Enums\VerificationStatus;
use App\Models\Category;
use App\Models\Household;
use App\Models\Product;
use App\Models\PurchaseTransaction;
use App\Models\ShoppingList;
use App\Models\ShoppingTrip;
use App\Models\StapleRule;
use App\Models\Store;
use App\Models\User;
use App\Services\ProductResolver;
use App\Services\StoreCategoryOrder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Sample data for the demo household: a few months of weekly shops with prices, so spending,
 * price history, and suggestions all have something to show, plus lists in progress.
 * Everything is fixed rather than random, so every reset looks the same.
 */
final class DemoHousehold
{
    /** Weeks of shopping history to create. */
    public const WEEKS = 16;

    /**
     * What the household buys: name, quantity, unit, category slug, and price in cents.
     * `every` is how many weeks apart it is bought, and `rise` is a weekly price change in
     * basis points, so price history shows a trend.
     *
     * @var list<array{name: string, quantity: string|null, unit: string|null, category: string, price: int, every: int, rise?: int}>
     */
    public const CATALOG = [
        ['name' => 'Apples', 'quantity' => '2', 'unit' => 'lb', 'category' => 'produce', 'price' => 398, 'every' => 1],
        ['name' => 'Bananas', 'quantity' => '6', 'unit' => null, 'category' => 'produce', 'price' => 174, 'every' => 1],
        ['name' => 'Spinach', 'quantity' => null, 'unit' => null, 'category' => 'produce', 'price' => 299, 'every' => 2],
        ['name' => 'Avocados', 'quantity' => '3', 'unit' => null, 'category' => 'produce', 'price' => 375, 'every' => 2],
        ['name' => 'Whole milk', 'quantity' => '1', 'unit' => 'gal', 'category' => 'dairy-eggs', 'price' => 389, 'every' => 1, 'rise' => 60],
        ['name' => 'Eggs', 'quantity' => '12', 'unit' => null, 'category' => 'dairy-eggs', 'price' => 349, 'every' => 1, 'rise' => 120],
        ['name' => 'Greek yogurt', 'quantity' => null, 'unit' => null, 'category' => 'dairy-eggs', 'price' => 549, 'every' => 2],
        ['name' => 'Cheddar', 'quantity' => null, 'unit' => null, 'category' => 'dairy-eggs', 'price' => 429, 'every' => 3],
        ['name' => 'Chicken breast', 'quantity' => '2', 'unit' => 'lb', 'category' => 'meat-seafood', 'price' => 898, 'every' => 1],
        ['name' => 'Ground beef', 'quantity' => '1', 'unit' => 'lb', 'category' => 'meat-seafood', 'price' => 649, 'every' => 2],
        ['name' => 'Salmon', 'quantity' => '1', 'unit' => 'lb', 'category' => 'meat-seafood', 'price' => 1199, 'every' => 3],
        ['name' => 'Frozen peas', 'quantity' => null, 'unit' => null, 'category' => 'frozen', 'price' => 249, 'every' => 3],
        ['name' => 'Ice cream', 'quantity' => null, 'unit' => null, 'category' => 'frozen', 'price' => 549, 'every' => 4],
        ['name' => 'Pasta', 'quantity' => '2', 'unit' => null, 'category' => 'pantry', 'price' => 358, 'every' => 2],
        ['name' => 'Rice', 'quantity' => '2', 'unit' => 'lb', 'category' => 'pantry', 'price' => 349, 'every' => 4],
        ['name' => 'Olive oil', 'quantity' => null, 'unit' => null, 'category' => 'pantry', 'price' => 999, 'every' => 6],
        ['name' => 'Coffee', 'quantity' => '1', 'unit' => 'lb', 'category' => 'beverages', 'price' => 1199, 'every' => 2, 'rise' => 150],
        ['name' => 'Sparkling water', 'quantity' => '12', 'unit' => null, 'category' => 'beverages', 'price' => 549, 'every' => 2],
        ['name' => 'Granola bars', 'quantity' => null, 'unit' => null, 'category' => 'snacks', 'price' => 449, 'every' => 2],
        ['name' => 'Tortilla chips', 'quantity' => null, 'unit' => null, 'category' => 'snacks', 'price' => 399, 'every' => 3],
        ['name' => 'Paper towels', 'quantity' => null, 'unit' => null, 'category' => 'household', 'price' => 1299, 'every' => 4],
        ['name' => 'Dish soap', 'quantity' => null, 'unit' => null, 'category' => 'household', 'price' => 349, 'every' => 5],
        ['name' => 'Toothpaste', 'quantity' => null, 'unit' => null, 'category' => 'personal-care', 'price' => 399, 'every' => 6],
        ['name' => 'Dog food', 'quantity' => null, 'unit' => null, 'category' => 'pets', 'price' => 2499, 'every' => 4],
    ];

    /** Sales tax applied to each shop, in basis points. */
    private const TAX = 300;

    /** @var array<string, Product> */
    private array $products = [];

    /** @var array<string, Category> */
    private array $categories = [];

    public function __construct(
        private readonly ProductResolver $resolver,
        private readonly SaveCategory $saveCategory,
        private readonly StoreCategoryOrder $order,
        private readonly CreateShoppingList $createList,
        private readonly AddListItem $addItem,
    ) {}

    public function fill(Household $household, User $user): void
    {
        $this->products = [];
        $this->categories = Category::query()->where('scope_type', Category::SYSTEM)->get()->keyBy('slug')->all();
        $this->categories['snacks'] = $this->saveCategory->create($household, ['name' => 'Snacks', 'sort_order' => 75]);

        $grocer = Store::query()->create(['scope_type' => 'household', 'scope_id' => $household->id, 'name' => 'Green Grocer', 'location' => 'Main Street']);
        $bigBox = Store::query()->create(['scope_type' => 'household', 'scope_id' => $household->id, 'name' => 'Big Box Market', 'location' => 'Route 9']);
        $bakery = Store::query()->create(['scope_type' => 'household', 'scope_id' => $household->id, 'name' => 'Corner Bakery']);

        $this->order->save($grocer, array_map(
            fn (string $slug): string => $this->categories[$slug]->uuid,
            ['produce', 'bakery', 'dairy-eggs', 'meat-seafood', 'pantry', 'snacks', 'frozen', 'beverages', 'household'],
        ));

        $weekly = $this->createList->handle($user, $household, ['title' => 'Weekly groceries', 'default_store_id' => $grocer->id]);
        $this->history($household, $user, $weekly, $grocer, $bigBox, $bakery);
        $this->currentLists($household, $user, $weekly, $bigBox);

        foreach (['Coffee' => 14, 'Paper towels' => 28, 'Dish soap' => 35] as $name => $days) {
            StapleRule::query()->create([
                'owner_type' => 'household',
                'owner_id' => $household->id,
                'product_id' => $this->product($household, $name)->id,
                'cadence_days' => $days,
            ]);
        }

        $user->favoriteLists()->attach($weekly->id);
        $user->favoriteProducts()->attach($this->product($household, 'Coffee')->id);
    }

    /**
     * One big shop a week, alternating between two stores, plus a bakery stop every other week.
     */
    private function history(Household $household, User $user, ShoppingList $list, Store $grocer, Store $bigBox, Store $bakery): void
    {
        $lastSaturday = CarbonImmutable::now($household->timezone)->startOfDay()->previous(CarbonImmutable::SATURDAY);

        for ($week = self::WEEKS; $week >= 1; $week--) {
            $day = $lastSaturday->subWeeks($week - 1);
            $store = $week % 3 === 0 ? $bigBox : $grocer;
            $items = array_values(array_filter(self::CATALOG, fn (array $item): bool => $week % $item['every'] === 0));

            $this->trip($household, $user, $list, $store, $day->setTime(10, 0), $items, self::WEEKS - $week);

            if ($week % 2 === 0) {
                $this->manualPurchase($household, $user, $bakery, $day->subDays(3), [
                    ['name' => 'Sourdough bread', 'category' => 'bakery', 'price' => 599],
                    ['name' => 'Bagels', 'category' => 'bakery', 'price' => 449],
                ]);
            }
        }
    }

    /**
     * @param  list<array{name: string, quantity: string|null, unit: string|null, category: string, price: int, every: int, rise?: int}>  $items
     */
    private function trip(Household $household, User $user, ShoppingList $list, Store $store, CarbonImmutable $at, array $items, int $weeksIn): void
    {
        $startedAt = $at->utc();
        $trip = ShoppingTrip::query()->create([
            'shopping_list_id' => $list->id,
            'store_id' => $store->id,
            'status' => TripStatus::Completed,
            'started_by' => $user->id,
            'completed_by' => $user->id,
            'started_at' => $startedAt,
            'completed_at' => $startedAt->addMinutes(40),
        ]);

        $lines = [];

        foreach ($items as $index => $item) {
            $product = $this->product($household, $item['name']);
            $category = $this->categories[$item['category']];
            // Prices drift a little week to week, and some climb steadily.
            $price = (int) round($item['price'] * (10_000 + ($item['rise'] ?? 0) * $weeksIn + (($weeksIn + $index) % 3 - 1) * 150) / 10_000);

            $lines[] = [$trip->items()->create([
                'product_id' => $product->id,
                'category_id' => $category->id,
                'name_snapshot' => $item['name'],
                'category_name_snapshot' => $category->name,
                'category_sort_snapshot' => $category->sort_order,
                'planned_quantity' => $item['quantity'],
                'planned_unit' => $item['unit'],
                'line_total_minor' => $price,
                'purchased' => true,
                'checked_by' => $user->id,
                'checked_at' => $startedAt->addMinutes(5 + $index),
                'sort_order' => $index + 1,
            ]), $category];
        }

        $subtotal = array_sum(array_map(fn (array $line): int => (int) $line[0]->line_total_minor, $lines));
        $tax = intdiv($subtotal * self::TAX, 10_000);
        $trip->update(['total_minor' => $subtotal + $tax]);

        $purchase = PurchaseTransaction::query()->create([
            'owner_type' => 'household',
            'owner_id' => $household->id,
            'shopping_trip_id' => $trip->id,
            'store_id' => $store->id,
            'merchant_name_snapshot' => $store->name,
            'purchased_at' => $startedAt->addMinutes(40),
            'currency_code' => $household->currency_code,
            'subtotal_minor' => $subtotal,
            'tax_minor' => $tax,
            'total_minor' => $subtotal + $tax,
            'entry_method' => EntryMethod::Trip,
            'verification_status' => VerificationStatus::Verified,
            'source_kind' => 'trip',
            'source_identifier' => (string) $trip->id,
            'created_by' => $user->id,
        ]);

        foreach ($lines as [$tripItem, $category]) {
            $purchase->items()->create([
                'source_trip_item_id' => $tripItem->id,
                'product_id' => $tripItem->product_id,
                'description_snapshot' => $tripItem->name_snapshot,
                'quantity' => $tripItem->planned_quantity,
                'unit_code' => $tripItem->planned_unit,
                'line_total_minor' => $tripItem->line_total_minor,
                'category_id' => $category->id,
                'category_name_snapshot' => $category->name,
                'is_verified' => true,
            ]);
        }

        $this->backdate([$trip, $purchase], $startedAt);
    }

    /**
     * @param  list<array{name: string, category: string, price: int}>  $items
     */
    private function manualPurchase(Household $household, User $user, Store $store, CarbonImmutable $at, array $items): void
    {
        $total = array_sum(array_column($items, 'price'));

        $purchase = PurchaseTransaction::query()->create([
            'owner_type' => 'household',
            'owner_id' => $household->id,
            'store_id' => $store->id,
            'merchant_name_snapshot' => $store->name,
            'purchased_at' => $at->setTime(12, 0)->utc(),
            'currency_code' => $household->currency_code,
            'total_minor' => $total,
            'entry_method' => EntryMethod::Manual,
            'verification_status' => VerificationStatus::Verified,
            'source_kind' => 'manual',
            'source_identifier' => (string) Str::uuid7(),
            'created_by' => $user->id,
        ]);

        foreach ($items as $item) {
            $category = $this->categories[$item['category']];
            $purchase->items()->create([
                'product_id' => $this->product($household, $item['name'])->id,
                'description_snapshot' => $item['name'],
                'line_total_minor' => $item['price'],
                'category_id' => $category->id,
                'category_name_snapshot' => $category->name,
                'is_verified' => true,
            ]);
        }

        $this->backdate([$purchase], $at->utc());
    }

    private function currentLists(Household $household, User $user, ShoppingList $weekly, Store $bigBox): void
    {
        foreach (['2 lb apples', 'bananas', 'whole milk', 'eggs x 12', 'spinach', '2 lb chicken breast', 'sourdough bread', 'coffee', 'greek yogurt', 'pasta'] as $entry) {
            $this->addItem->handle($weekly, $user, ['name' => $entry]);
        }
        $weekly->items()->whereIn('name_snapshot', ['greek yogurt', 'pasta'])->update(['status' => ListItemStatus::Satisfied]);

        $restock = $this->createList->handle($user, $household, ['title' => 'Big Box restock', 'default_store_id' => $bigBox->id]);
        foreach (['paper towels', 'dish soap', 'toothpaste', 'dog food', 'sparkling water x 12', 'granola bars'] as $entry) {
            $this->addItem->handle($restock, $user, ['name' => $entry]);
        }

        $cookout = $this->createList->handle($user, $household, ['title' => 'Summer cookout', 'status' => ListStatus::Archived]);
        foreach (['burger buns', '2 lb ground beef', 'corn', 'watermelon', 'lemonade', 'tortilla chips'] as $entry) {
            $this->addItem->handle($cookout, $user, ['name' => $entry]);
        }

        $personal = $this->createList->handle($user, $user, ['title' => 'Pharmacy']);
        foreach (['vitamins', 'sunscreen', 'allergy medicine'] as $entry) {
            $this->addItem->handle($personal, $user, ['name' => $entry]);
        }
    }

    private function product(Household $household, string $name): Product
    {
        $key = ProductResolver::normalize($name);

        if (! isset($this->products[$key])) {
            $product = $this->resolver->resolve($household, $name);

            $match = collect(self::CATALOG)->firstWhere('name', $name);
            if ($match !== null && $product->default_category_id !== $this->categories[$match['category']]->id) {
                $product->update(['default_category_id' => $this->categories[$match['category']]->id]);
            }

            $this->products[$key] = $product;
        }

        return $this->products[$key];
    }

    /**
     * @param  list<ShoppingTrip|PurchaseTransaction>  $models
     */
    private function backdate(array $models, CarbonImmutable $at): void
    {
        foreach ($models as $model) {
            $model->newQuery()->whereKey($model->getKey())->update(['created_at' => $at, 'updated_at' => $at]);
        }
    }
}
