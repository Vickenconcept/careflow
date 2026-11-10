<?php

namespace App\Domains\Documents\Providers\OpenAi;

use App\Ai\Agents\MedicalDocumentExtractionAgent;
use App\Contracts\Documents\MedicalInformationExtractor;
use App\Domains\Documents\Data\StructuredExtractionResult;
use App\Models\PatientDocument;
use Laravel\Ai\Enums\Lab;

class OpenAiMedicalInformationExtractor implements MedicalInformationExtractor
{
    public const SCHEMA_VERSION = '2026-11-01';

    public function extract(PatientDocument $document, string $text, string $language): StructuredExtractionResult
    {
        $response = (new MedicalDocumentExtractionAgent)->prompt(
            prompt: implode("\n\n", [
                'Document filename: '.$document->original_filename,
                'Document category hint: '.$document->category->value,
                'Detected language: '.$language,
                'Extract structured medical information exactly as documented:',
                $this->truncate($text),
            ]),
            provider: Lab::OpenAI,
        );

        $payload = StructuredExtractionResult::emptySchema();
        $payload['document_type'] = $this->nullableString($response['document_type'] ?? $document->category->value);
        $payload['original_language'] = $this->nullableString($response['original_language'] ?? $language);
        $payload['document_date'] = $this->nullableString($response['document_date'] ?? null);
        $payload['issuing_institution'] = $this->nullableString($response['issuing_institution'] ?? null);
        $payload['clinician_name'] = $this->nullableString($response['clinician_name'] ?? null);
        $payload['patient_identifiers'] = $this->stringList($response['patient_identifiers'] ?? []);
        $payload['diagnoses_as_documented'] = $this->factList($response['diagnoses_as_documented'] ?? []);
        $payload['symptoms_as_documented'] = $this->factList($response['symptoms_as_documented'] ?? []);
        $payload['medical_history_as_documented'] = $this->factList($response['medical_history_as_documented'] ?? []);
        $payload['medications_as_documented'] = $this->factList($response['medications_as_documented'] ?? []);
        $payload['allergies_as_documented'] = $this->factList($response['allergies_as_documented'] ?? []);
        $payload['laboratory_results'] = $this->factList($response['laboratory_results'] ?? []);
        $payload['imaging_findings_as_documented'] = $this->factList($response['imaging_findings_as_documented'] ?? []);
        $payload['procedures_as_documented'] = $this->factList($response['procedures_as_documented'] ?? []);
        $payload['uncertain_sections'] = array_map(
            static fn (string $reason): array => ['field' => 'general', 'reason' => $reason],
            $this->stringList($response['uncertain_sections'] ?? []),
        );
        $payload['missing_or_unreadable_information'] = $this->stringList($response['missing_or_unreadable_information'] ?? []);
        $payload['source_references'] = array_map(
            static fn (string $value): array => ['label' => 'source', 'value' => $value],
            $this->stringList($response['source_references'] ?? [$document->original_filename]),
        );

        return new StructuredExtractionResult(
            payload: $payload,
            provider: 'openai',
            schemaVersion: self::SCHEMA_VERSION,
            isSynthetic: false,
        );
    }

    protected function truncate(string $text): string
    {
        return mb_strlen($text) > 12000 ? mb_substr($text, 0, 12000) : $text;
    }

    protected function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return list<string>
     */
    protected function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn ($item) => is_string($item) && trim($item) !== '')
            ->map(fn (string $item) => trim($item))
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, source_reference: string, confidence: float|null, review_status: string}>
     */
    protected function factList(mixed $value): array
    {
        return array_map(
            static fn (string $item): array => [
                'value' => $item,
                'source_reference' => 'openai_extraction',
                'confidence' => null,
                'review_status' => 'unreviewed',
            ],
            $this->stringList($value),
        );
    }
}
