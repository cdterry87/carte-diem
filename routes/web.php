<?php

use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DashboardNoticeController;
use App\Http\Controllers\Web\HistoryController;
use App\Http\Controllers\Web\HouseholdController;
use App\Http\Controllers\Web\InvitationController;
use App\Http\Controllers\Web\LegalController;
use App\Http\Controllers\Web\ListController;
use App\Http\Controllers\Web\ListItemController;
use App\Http\Controllers\Web\ListShareController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\OrganizeController;
use App\Http\Controllers\Web\PurchaseController;
use App\Http\Controllers\Web\SpendingController;
use App\Http\Controllers\Web\StapleController;
use App\Http\Controllers\Web\TripController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::get('privacy', [LegalController::class, 'privacy'])->name('legal.privacy');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::post('dashboard/notices/{notice}/dismiss', [DashboardNoticeController::class, 'dismiss'])->name('notices.dismiss');

    Route::get('lists', [ListController::class, 'index'])->name('lists.index');
    Route::post('lists', [ListController::class, 'store'])->name('lists.store');
    Route::get('lists/{list}', [ListController::class, 'show'])->name('lists.show');
    Route::patch('lists/{list}', [ListController::class, 'update'])->name('lists.update');
    Route::delete('lists/{list}', [ListController::class, 'destroy'])->name('lists.destroy');
    Route::post('lists/{list}/restore', [ListController::class, 'restore'])->name('lists.restore');
    Route::post('lists/{list}/archive', [ListController::class, 'archive'])->name('lists.archive');
    Route::post('lists/{list}/unarchive', [ListController::class, 'unarchive'])->name('lists.unarchive');
    Route::post('lists/{list}/duplicate', [ListController::class, 'duplicate'])->name('lists.duplicate');
    Route::post('lists/{list}/favorite', [ListController::class, 'favorite'])->name('lists.favorite');

    Route::get('lists/{list}/suggestions', [ListItemController::class, 'suggestions'])->name('lists.suggestions');
    Route::post('lists/{list}/items', [ListItemController::class, 'store'])->name('lists.items.store');
    Route::post('lists/{list}/items/reorder', [ListItemController::class, 'reorder'])->name('lists.items.reorder');
    Route::patch('lists/{list}/items/{item}', [ListItemController::class, 'update'])->name('lists.items.update');
    Route::delete('lists/{list}/items/{item}', [ListItemController::class, 'destroy'])->name('lists.items.destroy');
    Route::post('lists/{list}/items/{item}/restore', [ListItemController::class, 'restore'])->name('lists.items.restore');

    Route::post('products/{product}/favorite', [ListItemController::class, 'favoriteProduct'])->name('products.favorite');

    Route::post('lists/{list}/shares', [ListShareController::class, 'store'])->middleware('throttle:invitations')->name('lists.shares.store');
    Route::patch('lists/{list}/shares/{guest}', [ListShareController::class, 'update'])->name('lists.shares.update');
    Route::delete('lists/{list}/shares/{guest}', [ListShareController::class, 'destroy'])->name('lists.shares.destroy');
    Route::delete('lists/{list}/share-invitations/{invitation}', [ListShareController::class, 'revokeInvitation'])->name('lists.share-invitations.destroy');

    Route::post('lists/{list}/trips', [TripController::class, 'store'])->name('trips.store');
    Route::get('trips/{trip}', [TripController::class, 'show'])->name('trips.show');
    Route::patch('trips/{trip}/items/{item}', [TripController::class, 'updateItem'])->name('trips.items.update');
    Route::post('trips/{trip}/items', [TripController::class, 'includeItem'])->name('trips.items.include');
    Route::patch('trips/{trip}/amount', [TripController::class, 'updateAmount'])->name('trips.amount');
    Route::post('trips/{trip}/complete', [TripController::class, 'complete'])->name('trips.complete');
    Route::post('trips/{trip}/cancel', [TripController::class, 'cancel'])->name('trips.cancel');

    Route::get('history', [HistoryController::class, 'index'])->name('history.index');

    Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases.index');
    Route::get('purchases/create', [PurchaseController::class, 'create'])->name('purchases.create');
    Route::post('purchases', [PurchaseController::class, 'store'])->name('purchases.store');
    Route::get('purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show');
    Route::get('purchases/{purchase}/edit', [PurchaseController::class, 'edit'])->name('purchases.edit');
    Route::patch('purchases/{purchase}', [PurchaseController::class, 'update'])->name('purchases.update');
    Route::delete('purchases/{purchase}', [PurchaseController::class, 'destroy'])->name('purchases.destroy');
    Route::post('purchases/{purchase}/restore', [PurchaseController::class, 'restore'])->withTrashed()->name('purchases.restore');

    Route::post('purchases/{purchase}/move', [PurchaseController::class, 'move'])->name('purchases.move');

    Route::get('spending', [SpendingController::class, 'index'])->name('spending.index');
    Route::get('spending/prices', [SpendingController::class, 'prices'])->name('spending.prices');

    Route::get('households', [HouseholdController::class, 'index'])->name('households.index');
    Route::post('households', [HouseholdController::class, 'store'])->name('households.store');
    Route::get('households/{household}', [HouseholdController::class, 'show'])->name('households.show');
    Route::patch('households/{household}', [HouseholdController::class, 'update'])->name('households.update');
    Route::delete('households/{household}', [HouseholdController::class, 'destroy'])->name('households.destroy');
    Route::post('households/{household}/leave', [HouseholdController::class, 'leave'])->name('households.leave');
    Route::post('households/{household}/transfer', [HouseholdController::class, 'transfer'])->name('households.transfer');
    Route::patch('households/{household}/members/{member}', [HouseholdController::class, 'updateMember'])->name('households.members.update');
    Route::delete('households/{household}/members/{member}', [HouseholdController::class, 'removeMember'])->name('households.members.destroy');
    Route::post('households/{household}/invitations', [HouseholdController::class, 'invite'])->middleware('throttle:invitations')->name('households.invitations.store');
    Route::delete('households/{household}/invitations/{invitation}', [HouseholdController::class, 'revokeInvitation'])->name('households.invitations.destroy');

    Route::get('invitations/{token}', [InvitationController::class, 'showHousehold'])->name('invitations.show');
    Route::post('household-invitations/{invitation}/accept', [InvitationController::class, 'acceptHousehold'])->name('invitations.household.accept');
    Route::post('household-invitations/{invitation}/decline', [InvitationController::class, 'declineHousehold'])->name('invitations.household.decline');
    Route::get('list-invitations/{token}', [InvitationController::class, 'showList'])->name('list-invitations.show');
    Route::post('list-invitations/{invitation}/accept', [InvitationController::class, 'acceptList'])->name('invitations.list.accept');
    Route::post('list-invitations/{invitation}/decline', [InvitationController::class, 'declineList'])->name('invitations.list.decline');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');

    Route::get('staples', [StapleController::class, 'index'])->name('staples.index');
    Route::post('staples', [StapleController::class, 'store'])->name('staples.store');
    Route::patch('staples/{staple}', [StapleController::class, 'update'])->name('staples.update');
    Route::delete('staples/{staple}', [StapleController::class, 'destroy'])->name('staples.destroy');
    Route::post('suggestions/{product}/dismiss', [StapleController::class, 'dismiss'])->name('suggestions.dismiss');
    Route::delete('suggestions/{product}/dismiss', [StapleController::class, 'lift'])->name('suggestions.lift');

    Route::get('organize', [OrganizeController::class, 'index'])->name('organize.index');
    Route::post('organize/stores', [OrganizeController::class, 'storeStore'])->name('organize.stores.store');
    Route::patch('organize/stores/{store}', [OrganizeController::class, 'updateStore'])->name('organize.stores.update');
    Route::delete('organize/stores/{store}', [OrganizeController::class, 'destroyStore'])->name('organize.stores.destroy');
    Route::put('organize/stores/{store}/category-order', [OrganizeController::class, 'updateCategoryOrder'])->name('organize.stores.category-order');
    Route::post('organize/categories', [OrganizeController::class, 'storeCategory'])->name('organize.categories.store');
    Route::patch('organize/categories/{category}', [OrganizeController::class, 'updateCategory'])->name('organize.categories.update');
    Route::delete('organize/categories/{category}', [OrganizeController::class, 'destroyCategory'])->name('organize.categories.destroy');
});

require __DIR__.'/settings.php';
