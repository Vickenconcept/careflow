<?php

namespace Tests\Support\Ai;

use App\Contracts\Documents\MedicalInformationExtractor;
use App\Domains\Documents\Data\StructuredExtractionResult;
use App\Models\PatientDocument;

class MockMedicalInformationExtractor implements MedicalInformationExtractor
{
    public function extract(PatientDocument $document, string $text, string $language): StructuredExtractionResult
    {
        $payload = StructuredExtractionResult::emptySchema();
        $payload['document_type'] = $document->category->value;
        $payload['original_language'] = $language;
        $payload['uncertain_sections'] = [
            [
                'field' => 'all_clinical_fields',
                'reason' => 'Mock provider does not invent clinical facts from documents.',
            ],
        ];
        $payload['missing_or_unreadable_information'] = [
            'No verified diagnoses extracted.',
            'No verified medications extracted.',
            'No verified laboratory values extracted.',
        ];
        $payload['source_references'] = [
            [
                'label' => 'original_filename',
                'value' => $document->original_filename,
            ],
            [
                'label' => 'extractor',
                'value' => 'mock',
            ],
        ];

        // Only copy values that are explicitly labeled in synthetic demo text.
        if (preg_match('/Reported diagnosis:\s*(.+)$/mi', $text, $match) === 1) {
            $value = trim($match[1]);

            if ($value !== '' && ! str_contains(mb_strtolower($value), 'not available')) {
                $payload['diagnoses_as_documented'][] = [
                    'value' => $value,
                    'source_reference' => 'synthetic_demo_text',
                    'confidence' => 0.4,
                    'review_status' => 'unreviewed',
                ];
            }
        }

        return new StructuredExtractionResult(
            payload: $payload,
            provider: 'mock',
            schemaVersion: '2026-11-01',
            isSynthetic: true,
        );
    }
}
