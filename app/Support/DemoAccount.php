<?php

namespace App\Support;

use App\Actions\Account\DeleteAccount;
use App\Actions\Households\CreateHousehold;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The shared demo account for the portfolio site: who it is, whether its one-click login is
 * offered, and how it is rebuilt.
 */
final class DemoAccount
{
    public function __construct(
        private readonly DeleteAccount $deleteAccount,
        private readonly CreateHousehold $createHousehold,
        private readonly DemoHousehold $household,
    ) {}

    public static function enabled(): bool
    {
        return (bool) config('demo.mode');
    }

    /**
     * Whether the demo account's credentials are set.
     */
    public static function configured(): bool
    {
        return filled(config('demo.email')) && filled(config('demo.password'));
    }

    /**
     * Whether the login page offers the one-click demo login.
     */
    public static function loginAvailable(): bool
    {
        return self::enabled() && self::configured();
    }

    public static function user(): ?User
    {
        return User::query()->where('is_demo', true)->first();
    }

    /**
     * Create the demo account, or throw away everything it changed and build it again.
     *
     * @throws RuntimeException when the credentials are missing, or a regular account already uses the email.
     */
    public function reset(): User
    {
        if (! self::configured()) {
            throw new RuntimeException('Set DEMO_EMAIL and DEMO_PASSWORD to create the demo account.');
        }

        /** @var string $email */
        $email = config('demo.email');

        return DB::transaction(function () use ($email): User {
            $existing = self::user();

            if ($existing === null && User::query()->where('email', $email)->exists()) {
                throw new RuntimeException("A regular account already uses {$email}, so it can't become the demo account.");
            }

            if ($existing !== null) {
                // Also ends every demo session, so nobody keeps a page open on deleted records.
                $this->deleteAccount->handle($existing, force: true);
            }

            $user = new User;
            $user->forceFill([
                'name' => config('demo.name'),
                'email' => $email,
                'password' => config('demo.password'),
                'timezone' => config('demo.timezone'),
                'email_verified_at' => now(),
                'is_demo' => true,
            ])->save();

            $household = $this->createHousehold->handle($user, ['name' => (string) config('demo.household')]);
            $this->household->fill($household, $user);

            return $user;
        });
    }
}
