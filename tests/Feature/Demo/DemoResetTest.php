<?php

use App\Models\Household;
use App\Models\PurchaseTransaction;
use App\Models\ShoppingList;
use App\Models\ShoppingTrip;
use App\Models\User;
use App\Support\DemoAccount;
use App\Support\DemoHousehold;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    config(['demo.email' => 'demo@example.com', 'demo.password' => 'password', 'demo.name' => 'Demo User', 'demo.household' => 'Demo Household', 'demo.timezone' => 'America/Chicago']);
});

it('creates the demo account with a household and months of history', function () {
    $this->artisan('demo:reset')->assertSuccessful();

    $demo = User::query()->sole();
    $household = Household::query()->sole();

    expect($demo)
        ->email->toBe('demo@example.com')
        ->is_demo->toBeTrue()
        ->timezone->toBe('America/Chicago')
        ->email_verified_at->not->toBeNull();
    expect($household->name)->toBe('Demo Household')
        ->and($household->owner_user_id)->toBe($demo->id);
    expect(ShoppingTrip::query()->count())->toBe(DemoHousehold::WEEKS)
        ->and(PurchaseTransaction::query()->min('purchased_at'))->toBeLessThan(now()->subMonths(3))
        ->and(ShoppingList::query()->count())->toBe(4);

    $this->actingAs($demo)->get(route('spending.index', ['household' => $household->uuid, 'months' => 6]))->assertOk();
    $this->get(route('dashboard'))->assertOk();
});

it('restores everything the demo user changed', function () {
    $first = app(DemoAccount::class)->reset();
    ShoppingList::query()->update(['title' => 'Vandalized']);
    ShoppingTrip::query()->delete();
    $first->update(['name' => 'Someone else']);

    $demo = app(DemoAccount::class)->reset();

    expect($demo->name)->toBe('Demo User')
        ->and(User::query()->count())->toBe(1)
        ->and(Household::withTrashed()->count())->toBe(1)
        ->and(ShoppingList::query()->where('title', 'Vandalized')->count())->toBe(0)
        ->and(ShoppingTrip::query()->count())->toBe(DemoHousehold::WEEKS);
});

it('leaves other accounts alone', function () {
    $regular = User::factory()->create();
    $list = ShoppingList::query()->create(['owner_type' => 'user', 'owner_id' => $regular->id, 'title' => 'Mine']);

    app(DemoAccount::class)->reset();
    app(DemoAccount::class)->reset();

    expect($regular->fresh())->not->toBeNull()
        ->and($list->fresh())->not->toBeNull();
});

it('never turns a regular account into the demo account', function () {
    User::factory()->create(['email' => 'demo@example.com']);

    $this->artisan('demo:reset')->assertFailed();

    expect(User::query()->where('is_demo', true)->exists())->toBeFalse();
});

it('fails without credentials', function (string $key) {
    config(["demo.{$key}" => null]);

    $this->artisan('demo:reset')->assertFailed();
    expect(User::query()->count())->toBe(0);
})->with(['email', 'password']);

it('runs daily only in demo mode', function (bool $mode) {
    config(['demo.mode' => $mode]);

    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command ?? '', 'demo:reset'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 0 * * *')
        ->and($event->filtersPass(app()))->toBe($mode);
})->with([true, false]);

it('seeds the demo account', function () {
    Artisan::call('db:seed', ['--force' => true]);

    expect(User::query()->where('is_demo', true)->count())->toBe(1);
});
