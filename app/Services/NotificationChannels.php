<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\NotificationPreference;
use App\Models\User;
use Carbon\CarbonImmutable;

class NotificationChannels
{
    /**
     * Channels for this user right now. Quiet hours hold back email, never the in-app feed.
     *
     * @return array<int, string>
     */
    public function for(User $user, NotificationType $type): array
    {
        $preference = $this->preference($user, $type);
        $channels = [];

        if ($preference['in_app']) {
            $channels[] = 'database';
        }

        if ($preference['email'] && $user->hasVerifiedEmail() && ! $this->isQuiet($user)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * @return array{in_app: bool, email: bool}
     */
    public function preference(User $user, NotificationType $type): array
    {
        $saved = NotificationPreference::query()->where('user_id', $user->id)->where('type', $type)->first();

        return [
            'in_app' => $saved !== null ? $saved->in_app : true,
            'email' => $saved !== null ? $saved->email : $type->defaultsToEmail(),
        ];
    }

    public function isQuiet(User $user): bool
    {
        if ($user->quiet_start === null || $user->quiet_end === null) {
            return false;
        }

        $now = CarbonImmutable::now($user->timezone)->format('H:i:s');
        $start = $user->quiet_start;
        $end = $user->quiet_end;

        return $start <= $end
            ? $now >= $start && $now < $end
            : $now >= $start || $now < $end;
    }
}
