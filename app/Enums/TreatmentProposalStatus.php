<?php

namespace App\Enums;

enum TreatmentProposalStatus: string
{
    case Draft = 'draft';
    case AwaitingClinicalApproval = 'awaiting_clinical_approval';
    case Approved = 'approved';
    case SentToPatient = 'sent_to_patient';
    case PatientAccepted = 'patient_accepted';
    case PatientDeclined = 'patient_declined';
    case Revised = 'revised';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::AwaitingClinicalApproval => 'Awaiting clinical approval',
            self::Approved => 'Approved',
            self::SentToPatient => 'Sent to patient',
            self::PatientAccepted => 'Patient accepted',
            self::PatientDeclined => 'Patient declined',
            self::Revised => 'Revised',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::AwaitingClinicalApproval], true);
    }

    public function canBeSentToPatient(): bool
    {
        return $this === self::Approved;
    }
}
