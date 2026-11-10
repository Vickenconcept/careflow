<?php

namespace App\Enums;

enum ConsentType: string
{
    case Enquiry = 'enquiry';
    case HealthData = 'health_data';
    case AiProcessing = 'ai_processing';

    public function label(): string
    {
        return match ($this) {
            self::Enquiry => 'Enquiry consent',
            self::HealthData => 'Health data processing consent',
            self::AiProcessing => 'AI processing consent',
        };
    }
}
