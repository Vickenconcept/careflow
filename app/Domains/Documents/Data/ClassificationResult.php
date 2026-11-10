<?php

namespace App\Domains\Documents\Data;

use App\Enums\DocumentCategory;

readonly class ClassificationResult
{
    public function __construct(
        public DocumentCategory $category,
        public ?float $confidence,
        public string $provider,
        public bool $isUncertain = false,
    ) {}
}
