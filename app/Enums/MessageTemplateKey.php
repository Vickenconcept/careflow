<?php

namespace App\Enums;

enum MessageTemplateKey: string
{
    case EnquiryReceived = 'enquiry_received';
    case AdditionalDocumentsRequired = 'additional_documents_required';
    case DocumentsReceived = 'documents_received';
    case CaseAssignedForReview = 'case_assigned_for_review';
    case EvaluationCompleted = 'evaluation_completed';
    case TreatmentProposalAvailable = 'treatment_proposal_available';
    case AppointmentConfirmation = 'appointment_confirmation';
    case TravelInformation = 'travel_information';
    case FollowUpReminder = 'follow_up_reminder';

    public function label(): string
    {
        return match ($this) {
            self::EnquiryReceived => 'Enquiry received',
            self::AdditionalDocumentsRequired => 'Additional documents required',
            self::DocumentsReceived => 'Documents received',
            self::CaseAssignedForReview => 'Case assigned for review',
            self::EvaluationCompleted => 'Evaluation completed',
            self::TreatmentProposalAvailable => 'Treatment proposal available',
            self::AppointmentConfirmation => 'Appointment confirmation',
            self::TravelInformation => 'Travel information',
            self::FollowUpReminder => 'Follow-up reminder',
        };
    }
}
