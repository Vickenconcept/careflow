<?php

namespace App\Events\Patients;

use App\Enums\CaseStatus;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CaseStatusUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $caseId,
        public string $from,
        public string $to,
        public int $actorUserId,
        public ?string $note = null,
        public bool $patientVisible = false,
    ) {}

    public function fromStatus(): CaseStatus
    {
        return CaseStatus::from($this->from);
    }

    public function toStatus(): CaseStatus
    {
        return CaseStatus::from($this->to);
    }
}
