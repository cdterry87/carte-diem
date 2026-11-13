<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Household;
use App\Models\Product;
use App\Models\PurchaseTransaction;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\ShoppingTrip;
use App\Models\StapleRule;
use App\Models\Store;
use App\Models\User;
use App\Observers\ShoppingListItemObserver;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();

        ShoppingListItem::observe(ShoppingListItemObserver::class);
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('invitations', fn (Request $request) => Limit::perHour(30)->by($request->user()?->id ?: $request->ip()));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Model::shouldBeStrict(! app()->isProduction());

        Relation::enforceMorphMap([
            'user' => User::class,
            'household' => Household::class,
            'audit_log' => AuditLog::class,
            'category' => Category::class,
            'product' => Product::class,
            'store' => Store::class,
            'staple_rule' => StapleRule::class,
            'shopping_list' => ShoppingList::class,
            'shopping_list_item' => ShoppingListItem::class,
            'shopping_trip' => ShoppingTrip::class,
            'purchase_transaction' => PurchaseTransaction::class,
        ]);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
