<?php

namespace App\Contracts\Documents;

use App\Domains\Documents\Data\LanguageDetectionResult;

interface DocumentLanguageDetector
{
    public function detect(string $text): LanguageDetectionResult;
}
