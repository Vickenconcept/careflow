<?php

namespace App\Domains\Documents\Data;

readonly class StructuredExtractionResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload,
        public string $provider,
        public string $schemaVersion,
        public bool $isSynthetic,
    ) {}

    /**
     * Spec-aligned empty schema. Missing values stay null/empty — never invented.
     *
     * @return array<string, mixed>
     */
    public static function emptySchema(): array
    {
        return [
            'document_type' => null,
            'original_language' => null,
            'document_date' => null,
            'issuing_institution' => null,
            'clinician_name' => null,
            'patient_identifiers' => [],
            'diagnoses_as_documented' => [],
            'symptoms_as_documented' => [],
            'medical_history_as_documented' => [],
            'medications_as_documented' => [],
            'allergies_as_documented' => [],
            'laboratory_results' => [],
            'imaging_findings_as_documented' => [],
            'procedures_as_documented' => [],
            'uncertain_sections' => [],
            'missing_or_unreadable_information' => [],
            'source_references' => [],
        ];
    }
}
