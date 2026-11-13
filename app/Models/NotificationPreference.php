<?php

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property NotificationType $type
 * @property bool $in_app
 * @property bool $email
 */
#[Fillable(['user_id', 'type', 'in_app', 'email'])]
class NotificationPreference extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'in_app' => 'boolean',
            'email' => 'boolean',
        ];
    }
}
