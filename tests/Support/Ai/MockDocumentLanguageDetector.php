<?php

namespace Tests\Support\Ai;

use App\Contracts\Documents\DocumentLanguageDetector;
use App\Domains\Documents\Data\LanguageDetectionResult;

class MockDocumentLanguageDetector implements DocumentLanguageDetector
{
    public function detect(string $text): LanguageDetectionResult
    {
        $normalized = mb_strtolower($text);

        $language = match (true) {
            str_contains($normalized, ' hasta ') || str_contains($normalized, ' tanı') => 'tr',
            preg_match('/[а-яё]/iu', $text) === 1 => 'ru',
            preg_match('/[\x{0600}-\x{06FF}]/u', $text) === 1 => 'ar',
            default => 'en',
        };

        return new LanguageDetectionResult(
            language: $language,
            confidence: 0.55,
            provider: 'mock',
            isUncertain: true,
        );
    }
}
