<?php

namespace App\Contracts\Documents;

use App\Domains\Documents\Data\ExtractedTextResult;
use App\Models\PatientDocument;

interface DocumentTextExtractor
{
    public function extract(PatientDocument $document): ExtractedTextResult;
}
