<?php

namespace App\Domains\Documents\Providers\Mock;

use App\Contracts\Documents\DocumentTranslator;
use App\Domains\Documents\Data\TranslationResult;

class MockDocumentTranslator implements DocumentTranslator
{
    public function translate(string $text, string $sourceLanguage, string $targetLanguage): TranslationResult
    {
        if ($sourceLanguage === $targetLanguage) {
            return new TranslationResult(
                translatedText: $text,
                sourceLanguage: $sourceLanguage,
                targetLanguage: $targetLanguage,
                provider: 'mock',
                isSynthetic: true,
            );
        }

        $translated = implode("\n", [
            '[MOCK TRANSLATION TO '.strtoupper($targetLanguage).']',
            'AI-assisted translation. Not a certified medical translation.',
            'Source language detected as '.$sourceLanguage.'.',
            'Original text follows unchanged because the mock provider does not generate clinical translations:',
            $text,
        ]);

        return new TranslationResult(
            translatedText: $translated,
            sourceLanguage: $sourceLanguage,
            targetLanguage: $targetLanguage,
            provider: 'mock',
            isSynthetic: true,
        );
    }
}
