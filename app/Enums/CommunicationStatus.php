<?php

namespace App\Enums;

enum CommunicationStatus: string
{
    case Draft = 'draft';
    case AwaitingApproval = 'awaiting_approval';
    case Approved = 'approved';
    case Sent = 'sent';
    case Failed = 'failed';
    case Logged = 'logged';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::AwaitingApproval => 'Awaiting Approval',
            self::Approved => 'Approved',
            self::Sent => 'Sent',
            self::Failed => 'Failed',
            self::Logged => 'Logged',
        };
    }
}
