<?php

namespace App\Events\Patients;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FollowUpScheduled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $followUpId,
        public int $caseId,
    ) {}
}
