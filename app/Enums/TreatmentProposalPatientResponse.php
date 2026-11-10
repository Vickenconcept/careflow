<?php

namespace App\Enums;

enum TreatmentProposalPatientResponse: string
{
    case Accepted = 'accepted';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Accepted => 'Accepted',
            self::Declined => 'Declined',
        };
    }

    public function resultingProposalStatus(): TreatmentProposalStatus
    {
        return match ($this) {
            self::Accepted => TreatmentProposalStatus::PatientAccepted,
            self::Declined => TreatmentProposalStatus::PatientDeclined,
        };
    }
}
