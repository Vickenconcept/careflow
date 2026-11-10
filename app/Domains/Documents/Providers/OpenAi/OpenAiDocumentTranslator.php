<?php

namespace App\Domains\Documents\Providers\OpenAi;

use App\Ai\Agents\DocumentTranslationAgent;
use App\Contracts\Documents\DocumentTranslator;
use App\Domains\Documents\Data\TranslationResult;
use Laravel\Ai\Enums\Lab;

class OpenAiDocumentTranslator implements DocumentTranslator
{
    public function translate(string $text, string $sourceLanguage, string $targetLanguage): TranslationResult
    {
        if ($sourceLanguage === $targetLanguage) {
            return new TranslationResult(
                translatedText: $text,
                sourceLanguage: $sourceLanguage,
                targetLanguage: $targetLanguage,
                provider: 'openai',
                isSynthetic: false,
            );
        }

        $response = (new DocumentTranslationAgent)->prompt(
            prompt: implode("\n\n", [
                sprintf('Translate from %s to %s.', strtoupper($sourceLanguage), strtoupper($targetLanguage)),
                'Source text:',
                $this->truncate($text),
            ]),
            provider: Lab::OpenAI,
        );

        $translated = trim((string) ($response['translated_text'] ?? ''));

        if ($translated === '') {
            $translated = $text;
        }

        return new TranslationResult(
            translatedText: $translated,
            sourceLanguage: $sourceLanguage,
            targetLanguage: $targetLanguage,
            provider: 'openai',
            isSynthetic: false,
        );
    }

    protected function truncate(string $text): string
    {
        return mb_strlen($text) > 12000 ? mb_substr($text, 0, 12000) : $text;
    }
}
