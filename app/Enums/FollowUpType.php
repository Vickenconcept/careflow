<?php

namespace App\Enums;

enum FollowUpType: string
{
    case ClinicalCheckIn = 'clinical_check_in';
    case Administrative = 'administrative';
    case TravelReturn = 'travel_return';
    case OutcomeReview = 'outcome_review';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::ClinicalCheckIn => 'Clinical check-in',
            self::Administrative => 'Administrative',
            self::TravelReturn => 'Travel return',
            self::OutcomeReview => 'Outcome review',
            self::Other => 'Other',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $type) => ['value' => $type->value, 'label' => $type->label()])
            ->values()
            ->all();
    }
}
