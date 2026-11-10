<?php

namespace App\Ai\Agents;

use App\Enums\DocumentCategory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(Lab::OpenAI)]
class DocumentClassificationAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return implode("\n", [
            'You classify medical documents for CareFlow case coordination.',
            'Choose exactly one category from the allowed enum.',
            'Use "unknown" when evidence is insufficient.',
            'Do not invent clinical facts. Classification only.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        $categories = array_map(
            static fn (DocumentCategory $category): string => $category->value,
            DocumentCategory::cases(),
        );

        return [
            'category' => $schema->string()->enum($categories)->required(),
            'confidence' => $schema->number()->min(0)->max(1)->required(),
            'is_uncertain' => $schema->boolean()->required(),
            'rationale' => $schema->string()->required(),
        ];
    }
}
