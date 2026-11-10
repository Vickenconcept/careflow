<?php

namespace App\Enums;

enum AppointmentType: string
{
    case Consultation = 'consultation';
    case Procedure = 'procedure';
    case FollowUp = 'follow_up';
    case Telemedicine = 'telemedicine';
    case Diagnostics = 'diagnostics';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Consultation => 'Consultation',
            self::Procedure => 'Procedure',
            self::FollowUp => 'Follow-up',
            self::Telemedicine => 'Telemedicine',
            self::Diagnostics => 'Diagnostics',
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
