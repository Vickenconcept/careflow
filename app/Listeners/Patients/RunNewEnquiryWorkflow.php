<?php

namespace App\Listeners\Patients;

use App\Domains\Workflows\WorkflowRunner;
use App\Enums\WorkflowKey;
use App\Events\Patients\PatientEnquirySubmitted;
use App\Models\PatientCase;

class RunNewEnquiryWorkflow
{
    public function __construct(private WorkflowRunner $workflows) {}

    public function handle(PatientEnquirySubmitted $event): void
    {
        $case = PatientCase::query()->find($event->caseId);

        if ($case === null) {
            return;
        }

        $this->workflows->run(
            case: $case,
            workflowKey: WorkflowKey::NewPatientEnquiry,
            idempotencyKey: 'new_enquiry:'.$case->id,
            context: [
                'enquiry_id' => $event->enquiryId,
                'trigger' => WorkflowKey::NewPatientEnquiry->trigger(),
            ],
        );
    }
}
