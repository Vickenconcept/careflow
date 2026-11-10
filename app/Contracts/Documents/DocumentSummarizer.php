<?php

namespace App\Contracts\Documents;

use App\Domains\Documents\Data\StructuredExtractionResult;
use App\Domains\Documents\Data\SummaryResult;
use App\Models\PatientDocument;

interface DocumentSummarizer
{
    public function summarize(
        PatientDocument $document,
        string $text,
        StructuredExtractionResult $extraction,
    ): SummaryResult;
}
