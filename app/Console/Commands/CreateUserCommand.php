<?php

namespace App\Console\Commands;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Closure;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Creates an account from the terminal, which works while sign-up is closed in demo mode.
 * Options skip their questions. The password is always asked for, so it never lands in
 * shell history. The account is created already verified, since whoever runs this vouches
 * for the email.
 */
#[Signature('user:create
    {--name= : Display name}
    {--email= : Email address used to log in}
    {--timezone= : IANA time zone, such as America/Chicago}')]
#[Description('Create a user account from the terminal')]
class CreateUserCommand extends Command
{
    use PasswordValidationRules, ProfileValidationRules;

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->components->error('Run this in an interactive terminal, so it can ask for the password.');

            return self::FAILURE;
        }

        $data = [
            'name' => $this->option('name') ?? text('Name', required: true, validate: $this->check('name')),
            'email' => $this->option('email') ?? text('Email', required: true, validate: $this->check('email')),
            'password' => password('Password', required: true, validate: $this->check('password'), hint: 'At least 8 characters.'),
        ];
        $data['password_confirmation'] = password('Confirm password', required: true);
        $data['timezone'] = $this->option('timezone')
            ?? (text('Time zone', placeholder: 'UTC', validate: fn (string $value) => $value === '' ? null : $this->check('timezone')($value), hint: 'Press Enter for UTC, or type one such as America/Chicago.') ?: 'UTC');

        // Options skip the checks made while typing, so everything is validated again here.
        $validator = Validator::make($data, $this->rules(), $this->messages());

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = new User;
        $user->forceFill([
            'name' => trim((string) $data['name']),
            'email' => strtolower(trim((string) $data['email'])),
            'password' => $data['password'],
            'timezone' => $data['timezone'],
            'email_verified_at' => now(),
        ])->save();

        $this->components->info("Created {$user->email}. They can log in now.");

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'name' => $this->nameRules(),
            // The demo email stays free, because demo:reset refuses to overwrite a real account.
            'email' => [...$this->emailRules(), Rule::notIn(array_filter([config('demo.email')]))],
            'password' => $this->passwordRules(),
            'timezone' => ['required', 'string', 'timezone:all'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return ['email.not_in' => 'That email is reserved for the demo account.'];
    }

    /**
     * Validates one answer as it is typed, so a mistake shows before the next question.
     */
    private function check(string $field): Closure
    {
        return function (string $value) use ($field): ?string {
            /** @var array<int, mixed> $rules */
            $rules = $this->rules()[$field];
            $rules = array_values(array_filter($rules, fn ($rule) => $rule !== 'confirmed'));

            return Validator::make([$field => $value], [$field => $rules], $this->messages())->errors()->first($field) ?: null;
        };
    }
}
