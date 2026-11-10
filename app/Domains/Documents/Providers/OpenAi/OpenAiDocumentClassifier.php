<?php

namespace App\Domains\Documents\Providers\OpenAi;

use App\Ai\Agents\DocumentClassificationAgent;
use App\Contracts\Documents\DocumentClassifier;
use App\Domains\Documents\Data\ClassificationResult;
use App\Enums\DocumentCategory;
use App\Models\PatientDocument;
use Laravel\Ai\Enums\Lab;
use Throwable;

class OpenAiDocumentClassifier implements DocumentClassifier
{
    public function classify(PatientDocument $document, string $text): ClassificationResult
    {
        $response = (new DocumentClassificationAgent)->prompt(
            prompt: implode("\n\n", [
                'Filename: '.$document->original_filename,
                'Classify this medical document text:',
                $this->truncate($text),
            ]),
            provider: Lab::OpenAI,
        );

        try {
            $category = DocumentCategory::from((string) ($response['category'] ?? DocumentCategory::Unknown->value));
        } catch (Throwable) {
            $category = DocumentCategory::Unknown;
        }

        $confidence = isset($response['confidence']) ? (float) $response['confidence'] : null;
        $isUncertain = (bool) ($response['is_uncertain'] ?? $category === DocumentCategory::Unknown);

        return new ClassificationResult(
            category: $category,
            confidence: $confidence,
            provider: 'openai',
            isUncertain: $isUncertain || $category === DocumentCategory::Unknown,
        );
    }

    protected function truncate(string $text): string
    {
        return mb_strlen($text) > 8000 ? mb_substr($text, 0, 8000) : $text;
    }
}
