<?php

use App\Models\AuditLog;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\ListShareInvitation;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function dataList(Model $owner, string $title): ShoppingList
{
    $list = ShoppingList::query()->create([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
        'title' => $title,
    ]);
    $list->items()->create(['name_snapshot' => $title.' item', 'sort_order' => 1]);

    return $list;
}

it('lets unverified users delete their account', function () {
    $user = User::factory()->unverified()->create();
    $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');
    $this->assertGuest();
    expect(User::query()->find($user->id))->toBeNull();
});

it('removes email-linked records and sessions on account deletion', function () {
    $user = User::factory()->create(['email' => 'gone@example.com']);
    $household = Household::factory()->create();
    $list = dataList(User::factory()->create(), 'Shared');
    $otherInvitation = $household->invitations()->create(['email' => 'other@example.com', 'role' => 'member', 'token_hash' => hash('sha256', 'b'), 'expires_at' => now()->addDay()]);
    $household->invitations()->create(['email' => 'gone@example.com', 'role' => 'member', 'token_hash' => hash('sha256', 'a'), 'expires_at' => now()->addDay()]);
    ListShareInvitation::query()->create(['shopping_list_id' => $list->id, 'email' => 'gone@example.com', 'role' => 'viewer', 'token_hash' => hash('sha256', 'c'), 'expires_at' => now()->addDay()]);
    DB::table('password_reset_tokens')->insert(['email' => 'gone@example.com', 'token' => 'x', 'created_at' => now()]);
    DB::table('sessions')->insert(['id' => 's1', 'user_id' => $user->id, 'payload' => 'x', 'last_activity' => time()]);
    AuditLog::record($user, 'test.action');

    $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');

    expect(HouseholdInvitation::query()->pluck('id')->all())->toBe([$otherInvitation->id])
        ->and(ListShareInvitation::query()->count())->toBe(0)
        ->and(DB::table('password_reset_tokens')->count())->toBe(0)
        ->and(DB::table('sessions')->where('id', 's1')->exists())->toBeFalse()
        ->and(AuditLog::query()->where('action', 'test.action')->first()->actor_id)->toBeNull();
});

it('serves the privacy policy publicly with configured facts', function () {
    config(['legal.entity' => 'Example LLC', 'legal.contact_email' => 'privacy@example.com']);

    $this->get(route('legal.privacy'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('legal/privacy')
            ->where('legal.entity', 'Example LLC')
            ->where('legal.contact_email', 'privacy@example.com')
            ->where('legal.effective_date', null));
});
