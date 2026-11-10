<?php

namespace App\Enums;

enum CommunicationDirection: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';
    case Internal = 'internal';

    public function label(): string
    {
        return match ($this) {
            self::Inbound => 'Inbound',
            self::Outbound => 'Outbound',
            self::Internal => 'Internal',
        };
    }
}
