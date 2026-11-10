<?php

namespace App\Enums;

enum DoctorReviewOutcome: string
{
    case ReadyForProposal = 'ready_for_proposal';
    case NeedMoreInformation = 'need_more_information';

    public function label(): string
    {
        return match ($this) {
            self::ReadyForProposal => 'Ready for treatment proposal preparation',
            self::NeedMoreInformation => 'Additional information required',
        };
    }

    public function resultingCaseStatus(): CaseStatus
    {
        return match ($this) {
            self::ReadyForProposal => CaseStatus::TreatmentProposalPreparation,
            self::NeedMoreInformation => CaseStatus::AdditionalInformationRequired,
        };
    }

    public function resultingReviewStatus(): DoctorReviewStatus
    {
        return match ($this) {
            self::ReadyForProposal => DoctorReviewStatus::Completed,
            self::NeedMoreInformation => DoctorReviewStatus::AdditionalInfoRequested,
        };
    }
}
