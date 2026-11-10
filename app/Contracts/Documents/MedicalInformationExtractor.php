<?php

namespace App\Contracts\Documents;

use App\Domains\Documents\Data\StructuredExtractionResult;
use App\Models\PatientDocument;

interface MedicalInformationExtractor
{
    public function extract(PatientDocument $document, string $text, string $language): StructuredExtractionResult;
}
