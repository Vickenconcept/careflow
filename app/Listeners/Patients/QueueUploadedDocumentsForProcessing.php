<?php

namespace App\Listeners\Patients;

use App\Actions\Patients\QueuePatientDocumentForProcessing;
use App\Enums\DocumentProcessingStatus;
use App\Events\Patients\PatientEnquirySubmitted;
use App\Models\PatientDocument;

class QueueUploadedDocumentsForProcessing
{
    public function __construct(
        private QueuePatientDocumentForProcessing $queuePatientDocumentForProcessing,
    ) {}

    /**
     * Mark uploaded files pending and dispatch ProcessPatientDocument jobs.
     *
     * Runs synchronously so status updates immediately after intake; only the
     * heavy AI pipeline job is queued for background workers.
     */
    public function handle(PatientEnquirySubmitted $event): void
    {
        $documents = PatientDocument::query()
            ->where('patient_case_id', $event->caseId)
            ->where('processing_status', DocumentProcessingStatus::Uploaded)
            ->get();

        foreach ($documents as $document) {
            $this->queuePatientDocumentForProcessing->handle($document);
        }
    }
}
