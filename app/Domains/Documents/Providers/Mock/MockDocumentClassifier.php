<?php

namespace App\Domains\Documents\Providers\Mock;

use App\Contracts\Documents\DocumentClassifier;
use App\Domains\Documents\Data\ClassificationResult;
use App\Enums\DocumentCategory;
use App\Models\PatientDocument;

class MockDocumentClassifier implements DocumentClassifier
{
    public function classify(PatientDocument $document, string $text): ClassificationResult
    {
        $haystack = mb_strtolower($document->original_filename.' '.$text);

        $category = match (true) {
            str_contains($haystack, 'lab') || str_contains($haystack, 'laboratory') => DocumentCategory::LaboratoryReport,
            str_contains($haystack, 'mri') || str_contains($haystack, 'ct') || str_contains($haystack, 'imaging') => DocumentCategory::ImagingReport,
            str_contains($haystack, 'discharge') => DocumentCategory::DischargeSummary,
            str_contains($haystack, 'prescription') || str_contains($haystack, 'rx') => DocumentCategory::Prescription,
            str_contains($haystack, 'referral') => DocumentCategory::ReferralLetter,
            str_contains($haystack, 'consult') => DocumentCategory::ConsultationReport,
            str_contains($haystack, 'history') => DocumentCategory::MedicalHistory,
            str_contains($haystack, 'report') => DocumentCategory::MedicalReport,
            default => DocumentCategory::Unknown,
        };

        return new ClassificationResult(
            category: $category,
            confidence: $category === DocumentCategory::Unknown ? 0.2 : 0.6,
            provider: 'mock',
            isUncertain: $category === DocumentCategory::Unknown,
        );
    }
}
