<?php

namespace App\Enums;

enum CaseStatus: string
{
    case NewEnquiry = 'new_enquiry';
    case AwaitingDocuments = 'awaiting_documents';
    case DocumentsReceived = 'documents_received';
    case AiProcessing = 'ai_processing';
    case AwaitingCoordinatorReview = 'awaiting_coordinator_review';
    case AwaitingDoctorReview = 'awaiting_doctor_review';
    case AdditionalInformationRequired = 'additional_information_required';
    case TreatmentProposalPreparation = 'treatment_proposal_preparation';
    case ProposalSent = 'proposal_sent';
    case AwaitingPatientResponse = 'awaiting_patient_response';
    case AppointmentPlanning = 'appointment_planning';
    case TravelCoordination = 'travel_coordination';
    case TreatmentScheduled = 'treatment_scheduled';
    case TreatmentCompleted = 'treatment_completed';
    case FollowUp = 'follow_up';
    case Closed = 'closed';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::NewEnquiry => 'New Enquiry',
            self::AwaitingDocuments => 'Awaiting Documents',
            self::DocumentsReceived => 'Documents Received',
            self::AiProcessing => 'AI Processing',
            self::AwaitingCoordinatorReview => 'Awaiting Coordinator Review',
            self::AwaitingDoctorReview => 'Awaiting Doctor Review',
            self::AdditionalInformationRequired => 'Additional Information Required',
            self::TreatmentProposalPreparation => 'Treatment Proposal Preparation',
            self::ProposalSent => 'Proposal Sent',
            self::AwaitingPatientResponse => 'Awaiting Patient Response',
            self::AppointmentPlanning => 'Appointment Planning',
            self::TravelCoordination => 'Travel Coordination',
            self::TreatmentScheduled => 'Treatment Scheduled',
            self::TreatmentCompleted => 'Treatment Completed',
            self::FollowUp => 'Follow-up',
            self::Closed => 'Closed',
            self::Declined => 'Declined',
        };
    }

    /**
     * Patient-safe status labels. Clinical / AI internals are never exposed.
     */
    public function patientLabel(): string
    {
        return match ($this) {
            self::NewEnquiry,
            self::DocumentsReceived,
            self::AiProcessing,
            self::AwaitingCoordinatorReview => 'Received',
            self::AwaitingDocuments,
            self::AdditionalInformationRequired => 'Awaiting your documents',
            self::AwaitingDoctorReview,
            self::TreatmentProposalPreparation => 'Under clinical review',
            self::ProposalSent,
            self::AwaitingPatientResponse => 'Proposal available',
            self::AppointmentPlanning,
            self::TravelCoordination,
            self::TreatmentScheduled => 'Appointment planning',
            self::TreatmentCompleted,
            self::FollowUp => 'Follow-up',
            self::Closed => 'Closed',
            self::Declined => 'Declined',
        };
    }
}
