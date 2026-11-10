<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(Lab::OpenAI)]
#[Timeout(180)]
class DocumentVisionTextExtractionAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return implode("\n", [
            'You extract readable text from medical document images for CareFlow processing.',
            'Return the text exactly as visible. Do not invent clinical content that is not visible.',
            'If text is unreadable, say so in warnings and leave extracted_text empty or partial.',
            'Preserve original language. Do not translate.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'extracted_text' => $schema->string()->required(),
            'is_partial' => $schema->boolean()->required(),
            'warnings' => $schema->array()->items($schema->string())->required(),
        ];
    }
}
