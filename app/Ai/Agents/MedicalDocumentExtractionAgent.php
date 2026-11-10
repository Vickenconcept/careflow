<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(Lab::OpenAI)]
class MedicalDocumentExtractionAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return implode("\n", [
            'You extract structured medical information exactly as documented.',
            'Never invent diagnoses, medications, labs, or findings that are not present in the source text.',
            'If a field is missing or unreadable, leave it empty and list it in missing_or_unreadable_information.',
            'Copy clinical wording as written; do not normalize into a clinical opinion.',
            'Mark uncertain content in uncertain_sections.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'document_type' => $schema->string()->nullable(),
            'original_language' => $schema->string()->nullable(),
            'document_date' => $schema->string()->nullable(),
            'issuing_institution' => $schema->string()->nullable(),
            'clinician_name' => $schema->string()->nullable(),
            'patient_identifiers' => $schema->array()->items($schema->string())->required(),
            'diagnoses_as_documented' => $schema->array()->items($schema->string())->required(),
            'symptoms_as_documented' => $schema->array()->items($schema->string())->required(),
            'medical_history_as_documented' => $schema->array()->items($schema->string())->required(),
            'medications_as_documented' => $schema->array()->items($schema->string())->required(),
            'allergies_as_documented' => $schema->array()->items($schema->string())->required(),
            'laboratory_results' => $schema->array()->items($schema->string())->required(),
            'imaging_findings_as_documented' => $schema->array()->items($schema->string())->required(),
            'procedures_as_documented' => $schema->array()->items($schema->string())->required(),
            'uncertain_sections' => $schema->array()->items($schema->string())->required(),
            'missing_or_unreadable_information' => $schema->array()->items($schema->string())->required(),
            'source_references' => $schema->array()->items($schema->string())->required(),
        ];
    }
}
