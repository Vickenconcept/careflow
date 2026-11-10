<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Proposed = 'proposed';
    case AwaitingConfirmation = 'awaiting_confirmation';
    case Confirmed = 'confirmed';
    case Rescheduled = 'rescheduled';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Proposed => 'Proposed',
            self::AwaitingConfirmation => 'Awaiting Confirmation',
            self::Confirmed => 'Confirmed',
            self::Rescheduled => 'Rescheduled',
            self::Cancelled => 'Cancelled',
            self::Completed => 'Completed',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Cancelled, self::Completed], true);
    }
}
