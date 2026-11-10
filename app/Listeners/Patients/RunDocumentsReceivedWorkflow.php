<?php

namespace App\Listeners\Patients;

use App\Domains\Workflows\WorkflowRunner;
use App\Enums\WorkflowKey;
use App\Events\Patients\DocumentProcessingCompleted;
use App\Models\PatientCase;

class RunDocumentsReceivedWorkflow
{
    public function __construct(private WorkflowRunner $workflows) {}

    public function handle(DocumentProcessingCompleted $event): void
    {
        $case = PatientCase::query()->find($event->caseId);

        if ($case === null) {
            return;
        }

        $this->workflows->run(
            case: $case,
            workflowKey: WorkflowKey::MedicalDocumentsReceived,
            idempotencyKey: 'documents_processed:'.$event->documentId,
            context: [
                'document_id' => $event->documentId,
                'trigger' => WorkflowKey::MedicalDocumentsReceived->trigger(),
            ],
        );
    }
}
