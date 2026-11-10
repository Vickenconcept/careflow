<?php

namespace App\Domains\Proposals;

use App\Enums\TreatmentProposalStatus;

class TreatmentProposalTransitionMap
{
    /**
     * @return list<TreatmentProposalStatus>
     */
    public function allowedFrom(TreatmentProposalStatus $from): array
    {
        return match ($from) {
            TreatmentProposalStatus::Draft => [
                TreatmentProposalStatus::AwaitingClinicalApproval,
                TreatmentProposalStatus::Cancelled,
            ],
            TreatmentProposalStatus::AwaitingClinicalApproval => [
                TreatmentProposalStatus::Approved,
                TreatmentProposalStatus::Draft,
                TreatmentProposalStatus::Cancelled,
            ],
            TreatmentProposalStatus::Approved => [
                TreatmentProposalStatus::SentToPatient,
                TreatmentProposalStatus::Cancelled,
                TreatmentProposalStatus::Expired,
            ],
            TreatmentProposalStatus::SentToPatient => [
                TreatmentProposalStatus::PatientAccepted,
                TreatmentProposalStatus::PatientDeclined,
                TreatmentProposalStatus::Expired,
                TreatmentProposalStatus::Cancelled,
            ],
            TreatmentProposalStatus::PatientAccepted,
            TreatmentProposalStatus::PatientDeclined,
            TreatmentProposalStatus::Revised,
            TreatmentProposalStatus::Expired,
            TreatmentProposalStatus::Cancelled => [],
        };
    }

    public function canTransition(TreatmentProposalStatus $from, TreatmentProposalStatus $to): bool
    {
        return in_array($to, $this->allowedFrom($from), true);
    }
}
