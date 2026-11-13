<?php

namespace App\Enums;

enum NotificationType: string
{
    case Invitation = 'invitation';
    case ReminderDue = 'reminder_due';
    case ListActivity = 'list_activity';
    case TripCompleted = 'trip_completed';

    public function defaultsToEmail(): bool
    {
        return in_array($this, [self::ReminderDue], true);
    }

    /**
     * Invitation emails always go out, because the person may have no account yet.
     */
    public function emailIsOptional(): bool
    {
        return $this !== self::Invitation;
    }
}
