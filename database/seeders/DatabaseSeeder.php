<?php

namespace Database\Seeders;

use App\Actions\Households\CreateHousehold;
use App\Actions\Lists\AddListItem;
use App\Actions\Lists\CreateShoppingList;
use App\Enums\EntryMethod;
use App\Enums\HouseholdRole;
use App\Enums\VerificationStatus;
use App\Models\Household;
use App\Models\PurchaseTransaction;
use App\Models\StapleRule;
use App\Models\Store;
use App\Models\User;
use App\Services\ProductResolver;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Local demo data. Every account uses the password "password".
     */
    public function run(): void
    {
        $sam = User::factory()->create(['name' => 'Sam Rivera', 'email' => 'sam@cartediem.test']);
        $alex = User::factory()->create(['name' => 'Alex Rivera', 'email' => 'alex@cartediem.test']);

        $household = app(CreateHousehold::class)->handle($sam, ['name' => 'Rivera home']);
        $household->addMember($alex, HouseholdRole::Member);

        $store = Store::query()->create([
            'scope_type' => 'household',
            'scope_id' => $household->id,
            'name' => 'Corner Market',
        ]);

        $list = app(CreateShoppingList::class)->handle($sam, $household, [
            'title' => 'Weekly groceries',
            'default_store_id' => $store->id,
        ]);

        foreach (['2 lb apples', 'whole milk', 'eggs x 12', 'sourdough bread', 'paper towels'] as $name) {
            app(AddListItem::class)->handle($list, $sam, ['name' => $name]);
        }

        $this->seedPurchaseHistory($household, $store, $sam);

        StapleRule::query()->create([
            'owner_type' => 'household',
            'owner_id' => $household->id,
            'product_id' => app(ProductResolver::class)->resolve($household, 'Dish soap')->id,
            'cadence_days' => 30,
        ]);
    }

    /**
     * Past purchases, so suggestions have real history to explain themselves with.
     */
    private function seedPurchaseHistory(Household $household, Store $store, User $user): void
    {
        foreach ([36, 27, 18, 9] as $index => $daysAgo) {
            $purchase = PurchaseTransaction::query()->create([
                'owner_type' => 'household',
                'owner_id' => $household->id,
                'store_id' => $store->id,
                'merchant_name_snapshot' => $store->name,
                'purchased_at' => now()->subDays($daysAgo),
                'total_minor' => 2150 + $index * 375,
                'entry_method' => EntryMethod::Manual,
                'verification_status' => VerificationStatus::Verified,
                'source_kind' => 'seed',
                'source_identifier' => (string) $index,
                'created_by' => $user->id,
            ]);

            foreach (['Coffee' => 899 + $index * 50, 'Bananas' => 129] as $name => $price) {
                $product = app(ProductResolver::class)->resolve($household, $name);

                $purchase->items()->create([
                    'product_id' => $product->id,
                    'description_snapshot' => $name,
                    'line_total_minor' => $price,
                    'category_id' => $product->default_category_id,
                    'is_verified' => true,
                ]);
            }
        }
    }
}
