<?php

namespace App\Enums;

enum AppointmentConfirmationSource: string
{
    case ManualHospitalVerified = 'manual_hospital_verified';
    case ExternalHis = 'external_his';

    public function label(): string
    {
        return match ($this) {
            self::ManualHospitalVerified => 'Manual hospital verification',
            self::ExternalHis => 'Hospital system booking reference (entered by staff)',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $source) => ['value' => $source->value, 'label' => $source->label()])
            ->values()
            ->all();
    }
}
