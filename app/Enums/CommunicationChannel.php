<?php

namespace App\Enums;

enum CommunicationChannel: string
{
    case Email = 'email';
    case WhatsApp = 'whatsapp';
    case Internal = 'internal';
    case PatientPortal = 'patient_portal';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::WhatsApp => 'WhatsApp (record a conversation)',
            self::Internal => 'Internal comment',
            self::PatientPortal => 'Patient portal',
            self::Other => 'Other',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $channel) => ['value' => $channel->value, 'label' => $channel->label()])
            ->values()
            ->all();
    }
}
