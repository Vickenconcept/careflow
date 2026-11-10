<?php

namespace App\Domains\Documents\Data;

readonly class ExtractedTextResult
{
    public function __construct(
        public string $text,
        public string $provider,
        public bool $isSynthetic,
        /** @var list<string> */
        public array $warnings = [],
    ) {}
}
