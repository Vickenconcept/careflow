<?php

namespace App\Enums;

enum DocumentCategory: string
{
    case MedicalReport = 'medical_report';
    case LaboratoryReport = 'laboratory_report';
    case ImagingReport = 'imaging_report';
    case DischargeSummary = 'discharge_summary';
    case Prescription = 'prescription';
    case ReferralLetter = 'referral_letter';
    case MedicalHistory = 'medical_history';
    case ConsultationReport = 'consultation_report';
    case OtherSupportingDocument = 'other_supporting_document';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::MedicalReport => 'Medical Report',
            self::LaboratoryReport => 'Laboratory Report',
            self::ImagingReport => 'Imaging Report',
            self::DischargeSummary => 'Discharge Summary',
            self::Prescription => 'Prescription',
            self::ReferralLetter => 'Referral Letter',
            self::MedicalHistory => 'Medical History',
            self::ConsultationReport => 'Consultation Report',
            self::OtherSupportingDocument => 'Other Supporting Document',
            self::Unknown => 'Unknown',
        };
    }
}
