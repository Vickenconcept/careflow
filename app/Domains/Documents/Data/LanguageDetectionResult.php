<?php

namespace App\Domains\Documents\Data;

readonly class LanguageDetectionResult
{
    public function __construct(
        public string $language,
        public ?float $confidence,
        public string $provider,
        public bool $isUncertain = false,
    ) {}
}
