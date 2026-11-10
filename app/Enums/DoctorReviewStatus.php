<?php

namespace App\Enums;

enum DoctorReviewStatus: string
{
    case Draft = 'draft';
    case InProgress = 'in_progress';
    case AdditionalInfoRequested = 'additional_info_requested';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::InProgress => 'In progress',
            self::AdditionalInfoRequested => 'Additional information requested',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Draft, self::InProgress], true);
    }
}
