<?php

namespace App\Domains\Documents\Data;

readonly class SummaryResult
{
    /**
     * @param  array<string, mixed>  $sections
     */
    public function __construct(
        public array $sections,
        public string $provider,
        public bool $isSynthetic,
        public string $disclaimer = 'AI-generated draft. Requires review by an authorized healthcare professional.',
    ) {}
}
