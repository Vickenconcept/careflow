<?php

namespace App\Contracts\Documents;

use App\Domains\Documents\Data\TranslationResult;

interface DocumentTranslator
{
    public function translate(string $text, string $sourceLanguage, string $targetLanguage): TranslationResult;
}
