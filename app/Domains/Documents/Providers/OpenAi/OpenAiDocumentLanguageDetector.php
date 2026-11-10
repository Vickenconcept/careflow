<?php

namespace App\Domains\Documents\Providers\OpenAi;

use App\Ai\Agents\DocumentLanguageDetectionAgent;
use App\Contracts\Documents\DocumentLanguageDetector;
use App\Domains\Documents\Data\LanguageDetectionResult;
use Laravel\Ai\Enums\Lab;

class OpenAiDocumentLanguageDetector implements DocumentLanguageDetector
{
    public function detect(string $text): LanguageDetectionResult
    {
        $response = (new DocumentLanguageDetectionAgent)->prompt(
            prompt: "Detect the primary language of this medical document text:\n\n".$this->truncate($text),
            provider: Lab::OpenAI,
        );

        $language = (string) ($response['language'] ?? 'other');
        $confidence = isset($response['confidence']) ? (float) $response['confidence'] : null;
        $isUncertain = (bool) ($response['is_uncertain'] ?? true);

        if (! in_array($language, ['en', 'tr', 'ar', 'ru', 'az'], true)) {
            $language = 'en';
            $isUncertain = true;
        }

        return new LanguageDetectionResult(
            language: $language,
            confidence: $confidence,
            provider: 'openai',
            isUncertain: $isUncertain || ($confidence !== null && $confidence < 0.6),
        );
    }

    protected function truncate(string $text): string
    {
        return mb_strlen($text) > 8000 ? mb_substr($text, 0, 8000) : $text;
    }
}
