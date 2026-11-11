<?php

namespace App\Enums;

enum CaseActivityType: string
{
    case CaseCreated = 'case_created';
    case StatusChanged = 'status_changed';
    case CoordinatorAssigned = 'coordinator_assigned';
    case DoctorAssigned = 'doctor_assigned';
    case AssignmentCleared = 'assignment_cleared';
    case DocumentUploaded = 'document_uploaded';
    case NoteAdded = 'note_added';
    case ClinicalObservationAdded = 'clinical_observation_added';
    case ClinicalRecommendationUpdated = 'clinical_recommendation_updated';
    case ClinicalRecommendationDecided = 'clinical_recommendation_decided';
    case DoctorReviewSubmitted = 'doctor_review_submitted';
    case TreatmentProposalCreated = 'treatment_proposal_created';
    case TreatmentProposalUpdated = 'treatment_proposal_updated';
    case TreatmentProposalSubmitted = 'treatment_proposal_submitted';
    case TreatmentProposalDecided = 'treatment_proposal_decided';
    case TreatmentProposalSent = 'treatment_proposal_sent';
    case TreatmentProposalPatientResponded = 'treatment_proposal_patient_responded';
    case AppointmentProposed = 'appointment_proposed';
    case AppointmentUpdated = 'appointment_updated';
    case AppointmentStatusChanged = 'appointment_status_changed';
    case TravelArrangementUpdated = 'travel_arrangement_updated';
    case TaskCreated = 'task_created';
    case TaskUpdated = 'task_updated';
    case TaskStatusChanged = 'task_status_changed';
    case CommunicationLogged = 'communication_logged';
    case CommunicationApproved = 'communication_approved';
    case FollowUpScheduled = 'follow_up_scheduled';
    case FollowUpUpdated = 'follow_up_updated';
    case FollowUpCompleted = 'follow_up_completed';
    case DataRetentionAction = 'data_retention_action';

    public function label(): string
    {
        return match ($this) {
            self::CaseCreated => 'Case created',
            self::StatusChanged => 'Status changed',
            self::CoordinatorAssigned => 'Coordinator assigned',
            self::DoctorAssigned => 'Doctor assigned',
            self::AssignmentCleared => 'Assignment cleared',
            self::DocumentUploaded => 'Document uploaded',
            self::NoteAdded => 'Note added',
            self::ClinicalObservationAdded => 'Clinical observation',
            self::ClinicalRecommendationUpdated => 'Clinical recommendation',
            self::ClinicalRecommendationDecided => 'Clinical approval decision',
            self::DoctorReviewSubmitted => 'Doctor review submitted',
            self::TreatmentProposalCreated => 'Treatment proposal created',
            self::TreatmentProposalUpdated => 'Treatment proposal updated',
            self::TreatmentProposalSubmitted => 'Treatment proposal submitted',
            self::TreatmentProposalDecided => 'Treatment proposal decided',
            self::TreatmentProposalSent => 'Treatment proposal sent',
            self::TreatmentProposalPatientResponded => 'Patient proposal response',
            self::AppointmentProposed => 'Appointment proposed',
            self::AppointmentUpdated => 'Appointment updated',
            self::AppointmentStatusChanged => 'Appointment status changed',
            self::TravelArrangementUpdated => 'Travel arrangement updated',
            self::TaskCreated => 'Task created',
            self::TaskUpdated => 'Task updated',
            self::TaskStatusChanged => 'Task status changed',
            self::CommunicationLogged => 'Communication logged',
            self::CommunicationApproved => 'Communication approved',
            self::FollowUpScheduled => 'Follow-up scheduled',
            self::FollowUpUpdated => 'Follow-up updated',
            self::FollowUpCompleted => 'Follow-up completed',
            self::DataRetentionAction => 'Data retention action',
        };
    }
}
