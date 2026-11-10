<?php

namespace App\Domains\Documents\Data;

readonly class TranslationResult
{
    public function __construct(
        public string $translatedText,
        public string $sourceLanguage,
        public string $targetLanguage,
        public string $provider,
        public bool $isSynthetic,
        public string $disclaimer = 'AI-assisted translation. Not a certified medical translation.',
    ) {}
}
