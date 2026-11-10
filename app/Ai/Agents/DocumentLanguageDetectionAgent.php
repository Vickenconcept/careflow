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
class DocumentLanguageDetectionAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return implode("\n", [
            'You detect the primary language of medical documents for an international hospital workflow.',
            'Return only what you can observe from the text.',
            'Prefer ISO 639-1 codes: en, tr, ar, ru, az.',
            'Use "other" when the language is unclear or unsupported.',
            'Set is_uncertain to true when confidence is low or the text is mixed/noisy.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'language' => $schema->string()->enum(['en', 'tr', 'ar', 'ru', 'az', 'other'])->required(),
            'confidence' => $schema->number()->min(0)->max(1)->required(),
            'is_uncertain' => $schema->boolean()->required(),
        ];
    }
}
