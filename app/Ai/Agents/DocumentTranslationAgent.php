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
class DocumentTranslationAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return implode("\n", [
            'You translate medical document text for care coordination.',
            'Preserve clinical meaning and uncertain wording.',
            'Do not add diagnoses, advice, or content absent from the source.',
            'If a term is ambiguous, keep the original term in parentheses.',
            'This is an AI-assisted draft translation, not a certified medical translation.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'translated_text' => $schema->string()->required(),
        ];
    }
}
