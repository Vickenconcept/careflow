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
class DocumentSummaryAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return implode("\n", [
            'You create a structured draft summary of a medical document for CareFlow staff review.',
            'Only include facts present in the source text or structured extraction.',
            'Separate facts_explicitly_found from uncertain_or_ambiguous_content.',
            'Never invent clinical conclusions. Label the output as requiring professional review.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'document_overview' => $schema->string()->required(),
            'document_date' => $schema->string()->nullable(),
            'reported_diagnosis' => $schema->array()->items($schema->string())->required(),
            'relevant_documented_history' => $schema->array()->items($schema->string())->required(),
            'reported_symptoms' => $schema->array()->items($schema->string())->required(),
            'medications_listed' => $schema->array()->items($schema->string())->required(),
            'allergies_listed' => $schema->array()->items($schema->string())->required(),
            'laboratory_findings' => $schema->array()->items($schema->string())->required(),
            'imaging_findings_as_written' => $schema->array()->items($schema->string())->required(),
            'procedures_or_treatments_documented' => $schema->array()->items($schema->string())->required(),
            'missing_or_unclear_information' => $schema->array()->items($schema->string())->required(),
            'source_document_references' => $schema->array()->items($schema->string())->required(),
            'facts_explicitly_found' => $schema->array()->items($schema->string())->required(),
            'uncertain_or_ambiguous_content' => $schema->array()->items($schema->string())->required(),
        ];
    }
}
