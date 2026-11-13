<?php

namespace App\Actions\Notifications;

use App\Enums\NotificationType;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\NotificationChannels;
use Illuminate\Support\Facades\DB;

class SaveNotificationSettings
{
    public function __construct(private readonly NotificationChannels $channels) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function handle(User $user, array $validated): void
    {
        DB::transaction(function () use ($user, $validated): void {
            /** @var array<int, array{type: string, in_app: bool, email: bool}> $preferences */
            $preferences = $validated['preferences'] ?? [];

            foreach ($preferences as $preference) {
                $type = NotificationType::from($preference['type']);

                NotificationPreference::query()->updateOrCreate(
                    ['user_id' => $user->id, 'type' => $type],
                    [
                        'in_app' => $preference['in_app'],
                        'email' => $type->emailIsOptional() && $preference['email'],
                    ],
                );
            }

            if (array_key_exists('quiet_start', $validated) || array_key_exists('quiet_end', $validated)) {
                $user->update([
                    'quiet_start' => $validated['quiet_start'] ?? null,
                    'quiet_end' => $validated['quiet_end'] ?? null,
                ]);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function current(User $user): array
    {
        return [
            'preferences' => collect(NotificationType::cases())->map(fn (NotificationType $type): array => [
                'type' => $type->value,
                'email_optional' => $type->emailIsOptional(),
                ...$this->channels->preference($user, $type),
            ])->all(),
            'quiet_start' => $user->quiet_start !== null ? substr($user->quiet_start, 0, 5) : null,
            'quiet_end' => $user->quiet_end !== null ? substr($user->quiet_end, 0, 5) : null,
        ];
    }
}
