<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use DateTimeZone;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * @var array<string, string>
     */
    private const RENAMED_TIMEZONES = [
        'America/Buenos_Aires' => 'America/Argentina/Buenos_Aires',
        'Asia/Calcutta' => 'Asia/Kolkata',
        'Asia/Katmandu' => 'Asia/Kathmandu',
        'Asia/Rangoon' => 'Asia/Yangon',
        'Asia/Saigon' => 'Asia/Ho_Chi_Minh',
        'Europe/Kiev' => 'Europe/Kyiv',
    ];

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => $this->nameRules(),
            'email' => $this->emailRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'timezone' => $this->detectedTimezone($input['timezone'] ?? null),
        ]);
    }

    /**
     * The browser supplies this without the user seeing it, so a value PHP does
     * not recognize falls back to UTC rather than blocking registration. Browsers
     * can report older names that some PHP builds no longer accept.
     */
    private function detectedTimezone(?string $timezone): string
    {
        $timezone = self::RENAMED_TIMEZONES[$timezone] ?? $timezone;

        return in_array($timezone, DateTimeZone::listIdentifiers(), true) ? (string) $timezone : 'UTC';
    }
}
