<?php

namespace App\Enums;

enum WorkflowKey: string
{
    case NewPatientEnquiry = 'new_patient_enquiry';
    case MedicalDocumentsReceived = 'medical_documents_received';
    case DoctorEvaluation = 'doctor_evaluation';
    case AdditionalDocuments = 'additional_documents';
    case TreatmentCoordination = 'treatment_coordination';
    case PostTreatmentFollowUp = 'post_treatment_follow_up';

    public function label(): string
    {
        return match ($this) {
            self::NewPatientEnquiry => 'New patient enquiry',
            self::MedicalDocumentsReceived => 'Medical documents received',
            self::DoctorEvaluation => 'Doctor evaluation',
            self::AdditionalDocuments => 'Additional documents',
            self::TreatmentCoordination => 'Treatment coordination',
            self::PostTreatmentFollowUp => 'Post-treatment follow-up',
        };
    }

    public function trigger(): string
    {
        return match ($this) {
            self::NewPatientEnquiry => 'patient_enquiry_submitted',
            self::MedicalDocumentsReceived => 'document_processing_completed',
            self::DoctorEvaluation => 'doctor_assigned',
            self::AdditionalDocuments => 'additional_information_required',
            self::TreatmentCoordination => 'treatment_proposal_accepted',
            self::PostTreatmentFollowUp => 'treatment_completed',
        };
    }
}
