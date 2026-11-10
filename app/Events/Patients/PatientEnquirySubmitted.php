<?php

namespace App\Events\Patients;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PatientEnquirySubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $enquiryId,
        public int $caseId,
        public string $portalToken = '',
    ) {}
}
