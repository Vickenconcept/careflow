<?php

namespace App\Enums;

enum ClinicalRecommendationStatus: string
{
    case Draft = 'draft';
    case AwaitingApproval = 'awaiting_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::AwaitingApproval => 'Awaiting clinical approval',
            self::Approved => 'Clinically approved',
            self::Rejected => 'Rejected',
            self::Superseded => 'Superseded',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::AwaitingApproval, self::Rejected], true);
    }
}
