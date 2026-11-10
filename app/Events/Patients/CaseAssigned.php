<?php

namespace App\Events\Patients;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CaseAssigned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $caseId,
        public int $actorUserId,
        public ?int $coordinatorUserId = null,
        public ?int $doctorUserId = null,
    ) {}
}
