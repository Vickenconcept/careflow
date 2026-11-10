<?php

namespace App\Domains\Cases;

use App\Enums\AppointmentStatus;
use App\Enums\CaseStatus;
use App\Enums\TreatmentProposalStatus;
use App\Exceptions\InvalidCaseStatusTransitionException;
use App\Models\CaseAppointment;
use App\Models\PatientCase;
use App\Models\TreatmentProposal;

/**
 * Default permitted transitions for Phase 1.
 *
 * Later this can be replaced/augmented by organization-configurable rules
 * without rewriting callers that depend on canTransition().
 */
class CaseStatusTransitionMap
{
    /**
     * @return list<CaseStatus>
     */
    public function allowedFrom(CaseStatus $from): array
    {
        return match ($from) {
            CaseStatus::NewEnquiry => [
                CaseStatus::AwaitingDocuments,
                CaseStatus::DocumentsReceived,
                CaseStatus::AwaitingCoordinatorReview,
                CaseStatus::Declined,
                CaseStatus::Closed,
            ],
            CaseStatus::AwaitingDocuments => [
                CaseStatus::DocumentsReceived,
                CaseStatus::AwaitingCoordinatorReview,
                CaseStatus::Declined,
                CaseStatus::Closed,
            ],
            CaseStatus::DocumentsReceived => [
                CaseStatus::AiProcessing,
                CaseStatus::AwaitingCoordinatorReview,
                CaseStatus::AwaitingDocuments,
                CaseStatus::Declined,
                CaseStatus::Closed,
            ],
            CaseStatus::AiProcessing => [
                CaseStatus::AwaitingCoordinatorReview,
                CaseStatus::DocumentsReceived,
                CaseStatus::AwaitingDocuments,
                CaseStatus::Declined,
            ],
            CaseStatus::AwaitingCoordinatorReview => [
                CaseStatus::AwaitingDoctorReview,
                CaseStatus::AdditionalInformationRequired,
                CaseStatus::AwaitingDocuments,
                CaseStatus::TreatmentProposalPreparation,
                CaseStatus::Declined,
                CaseStatus::Closed,
            ],
            CaseStatus::AwaitingDoctorReview => [
                CaseStatus::AdditionalInformationRequired,
                CaseStatus::TreatmentProposalPreparation,
                CaseStatus::AwaitingCoordinatorReview,
                CaseStatus::Declined,
            ],
            CaseStatus::AdditionalInformationRequired => [
                CaseStatus::AwaitingDocuments,
                CaseStatus::DocumentsReceived,
                CaseStatus::AwaitingCoordinatorReview,
                CaseStatus::AwaitingDoctorReview,
                CaseStatus::Declined,
                CaseStatus::Closed,
            ],
            CaseStatus::TreatmentProposalPreparation => [
                CaseStatus::ProposalSent,
                CaseStatus::AwaitingDoctorReview,
                CaseStatus::AwaitingCoordinatorReview,
                CaseStatus::AdditionalInformationRequired,
                CaseStatus::Declined,
            ],
            CaseStatus::ProposalSent => [
                CaseStatus::AwaitingPatientResponse,
                CaseStatus::TreatmentProposalPreparation,
                CaseStatus::Declined,
                CaseStatus::Closed,
            ],
            CaseStatus::AwaitingPatientResponse => [
                CaseStatus::AppointmentPlanning,
                CaseStatus::TreatmentProposalPreparation,
                CaseStatus::Declined,
                CaseStatus::Closed,
            ],
            CaseStatus::AppointmentPlanning => [
                CaseStatus::TravelCoordination,
                CaseStatus::TreatmentScheduled,
                CaseStatus::AwaitingPatientResponse,
                CaseStatus::Closed,
            ],
            CaseStatus::TravelCoordination => [
                CaseStatus::TreatmentScheduled,
                CaseStatus::AppointmentPlanning,
                CaseStatus::Closed,
            ],
            CaseStatus::TreatmentScheduled => [
                CaseStatus::TreatmentCompleted,
                CaseStatus::AppointmentPlanning,
                CaseStatus::Closed,
            ],
            CaseStatus::TreatmentCompleted => [
                CaseStatus::FollowUp,
                CaseStatus::Closed,
            ],
            CaseStatus::FollowUp => [
                CaseStatus::Closed,
                CaseStatus::TreatmentCompleted,
            ],
            CaseStatus::Closed, CaseStatus::Declined => [],
        };
    }

    public function canTransition(CaseStatus $from, CaseStatus $to): bool
    {
        if ($from === $to) {
            return false;
        }

        return in_array($to, $this->allowedFrom($from), true);
    }

    /**
     * Hard business prerequisites that cannot be expressed as a simple edge.
     */
    public function assertPrerequisites(PatientCase $case, CaseStatus $to): void
    {
        if ($to === CaseStatus::ProposalSent) {
            $hasSendableProposal = TreatmentProposal::query()
                ->where('patient_case_id', $case->id)
                ->where('is_current', true)
                ->whereIn('status', [
                    TreatmentProposalStatus::Approved->value,
                    TreatmentProposalStatus::SentToPatient->value,
                ])
                ->exists();

            if (! $hasSendableProposal) {
                throw InvalidCaseStatusTransitionException::missingPrerequisites(
                    $to,
                    'An approved treatment proposal is required before marking the proposal as sent.',
                );
            }
        }

        if ($to === CaseStatus::TreatmentScheduled) {
            $hasAcceptedProposal = TreatmentProposal::query()
                ->where('patient_case_id', $case->id)
                ->where('is_current', true)
                ->where('status', TreatmentProposalStatus::PatientAccepted->value)
                ->exists();

            if (! $hasAcceptedProposal) {
                throw InvalidCaseStatusTransitionException::missingPrerequisites(
                    $to,
                    'A patient-accepted treatment proposal is required before scheduling treatment.',
                );
            }

            $hasConfirmedAppointment = CaseAppointment::query()
                ->where('patient_case_id', $case->id)
                ->where('status', AppointmentStatus::Confirmed->value)
                ->where('hospital_slot_verified', true)
                ->whereNotNull('confirmation_source')
                ->whereNotNull('confirmed_starts_at')
                ->exists();

            if (! $hasConfirmedAppointment) {
                throw InvalidCaseStatusTransitionException::missingPrerequisites(
                    $to,
                    'A hospital-verified confirmed appointment is required before scheduling treatment.',
                );
            }
        }
    }
}
