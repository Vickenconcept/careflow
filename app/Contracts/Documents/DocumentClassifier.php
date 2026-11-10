<?php

namespace App\Contracts\Documents;

use App\Domains\Documents\Data\ClassificationResult;
use App\Models\PatientDocument;

interface DocumentClassifier
{
    public function classify(PatientDocument $document, string $text): ClassificationResult;
}
