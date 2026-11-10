<?php

namespace App\Events\Patients;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentProcessingCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $documentId,
        public int $caseId,
        public int $teamId,
    ) {}
}
