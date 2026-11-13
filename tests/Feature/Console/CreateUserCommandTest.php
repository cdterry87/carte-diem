<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates a verified account from the terminal, asking for the password', function () {
    $this->artisan('user:create', ['--name' => 'Pat Lee', '--email' => 'Pat@Example.com', '--timezone' => 'America/Chicago'])
        ->expectsQuestion('Password', 'correct-horse-9')
        ->expectsQuestion('Confirm password', 'correct-horse-9')
        ->assertSuccessful();

    $user = User::query()->sole();
    expect($user)
        ->name->toBe('Pat Lee')
        ->email->toBe('pat@example.com')
        ->timezone->toBe('America/Chicago')
        ->is_demo->toBeFalse()
        ->email_verified_at->not->toBeNull()
        ->and(Hash::check('correct-horse-9', $user->password))->toBeTrue();
});

it('asks for anything not given as an option', function () {
    $this->artisan('user:create')
        ->expectsQuestion('Name', 'Sam Kim')
        ->expectsQuestion('Email', 'sam@example.com')
        ->expectsQuestion('Password', 'correct-horse-9')
        ->expectsQuestion('Confirm password', 'correct-horse-9')
        ->expectsQuestion('Time zone', '')
        ->assertSuccessful();

    expect(User::query()->sole()->timezone)->toBe('UTC');
});

it('works while demo mode closes sign-up', function () {
    config(['demo.mode' => true]);

    $this->artisan('user:create', ['--name' => 'Pat', '--email' => 'pat@example.com', '--timezone' => 'UTC'])
        ->expectsQuestion('Password', 'correct-horse-9')
        ->expectsQuestion('Confirm password', 'correct-horse-9')
        ->assertSuccessful();
});

it('refuses taken, reserved, and invalid details', function (array $options, string $confirm = 'correct-horse-9') {
    User::factory()->create(['email' => 'taken@example.com']);
    config(['demo.email' => 'demo@example.com']);

    $this->artisan('user:create', ['--name' => 'Pat', '--timezone' => 'UTC', ...$options])
        ->expectsQuestion('Password', 'correct-horse-9')
        ->expectsQuestion('Confirm password', $confirm)
        ->assertFailed();

    expect(User::query()->count())->toBe(1);
})->with([
    'taken email' => [['--email' => 'taken@example.com']],
    'demo email' => [['--email' => 'demo@example.com']],
    'bad email' => [['--email' => 'not-an-email']],
    'bad time zone' => [['--email' => 'new@example.com', '--timezone' => 'Mars/Base']],
    'mismatched passwords' => [['--email' => 'new@example.com'], 'something-else-1'],
]);

it('refuses to run without a terminal to ask for the password', function () {
    $this->artisan('user:create', ['--name' => 'Pat', '--email' => 'pat@example.com', '--no-interaction' => true])->assertFailed();

    expect(User::query()->count())->toBe(0);
});
