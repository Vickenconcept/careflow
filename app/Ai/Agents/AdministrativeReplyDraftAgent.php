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
class AdministrativeReplyDraftAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return implode("\n", [
            'You draft administrative patient-coordination messages for CareFlow staff review.',
            'Never give clinical advice, diagnoses, medication guidance, or treatment decisions.',
            'Keep tone professional, clear, and empathetic.',
            'Drafts require human approval before sending.',
            'If clinical content is requested, refuse and explain that a clinician must respond.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'subject' => $schema->string()->nullable(),
            'body' => $schema->string()->required(),
            'contains_clinical_advice' => $schema->boolean()->required(),
            'warnings' => $schema->array()->items($schema->string())->required(),
        ];
    }
}
