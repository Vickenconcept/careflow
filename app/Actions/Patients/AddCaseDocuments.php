<?php

namespace App\Actions\Patients;

use App\Domains\Cases\CaseStatusTransitionMap;
use App\Enums\CaseActivityType;
use App\Enums\CaseStatus;
use App\Enums\DocumentCategory;
use App\Enums\DocumentProcessingStatus;
use App\Models\PatientCase;
use App\Models\PatientDocument;
use App\Models\User;
use App\Support\PatientDocumentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Store new documents on a case, whether the patient uploaded them in the portal
 * or a staff member added files the patient sent by email or WhatsApp.
 */
class AddCaseDocuments
{
    public function __construct(
        private PatientDocumentStorage $documentStorage,
        private RecordCaseActivity $recordActivity,
        private QueuePatientDocumentForProcessing $queueDocument,
        private CaseStatusTransitionMap $transitions,
    ) {}

    /**
     * @param  list<array{file: UploadedFile, category: DocumentCategory}>  $files
     * @param  User|null  $actor  The staff member adding the files, or null when the patient uploaded them.
     * @return list<PatientDocument>
     */
    public function handle(PatientCase $case, array $files, ?User $actor = null): array
    {
        $documents = DB::transaction(function () use ($case, $files, $actor) {
            /** @var PatientCase $locked */
            $locked = PatientCase::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();
            $stored = [];

            foreach ($files as $item) {
                $meta = $this->documentStorage->store($locked->team, $locked, $item['file']);

                $stored[] = PatientDocument::query()->create([
                    'team_id' => $locked->team_id,
                    'patient_id' => $locked->patient_id,
                    'patient_case_id' => $locked->id,
                    'uuid' => $meta['uuid'],
                    'original_filename' => $meta['original_filename'],
                    'disk' => $meta['disk'],
                    'path' => $meta['path'],
                    'mime_type' => $meta['mime_type'],
                    'extension' => $meta['extension'],
                    'size_bytes' => $meta['size_bytes'],
                    'checksum_sha256' => $meta['checksum_sha256'],
                    'category' => $item['category'],
                    'processing_status' => DocumentProcessingStatus::Uploaded,
                    'uploaded_at' => now(),
                ]);
            }

            $this->recordActivity->handle(
                case: $locked,
                type: CaseActivityType::DocumentUploaded,
                summary: $actor === null
                    ? sprintf('Patient uploaded %d additional document(s) through the portal', count($stored))
                    : sprintf('%s added %d document(s) received from the patient', $actor->name, count($stored)),
                actor: $actor,
                properties: ['document_uuids' => array_map(fn (PatientDocument $document) => $document->uuid, $stored)],
                patientVisible: $actor === null,
            );

            $from = $locked->status;
            $waitingForDocuments = in_array($from, [CaseStatus::NewEnquiry, CaseStatus::AwaitingDocuments, CaseStatus::AdditionalInformationRequired], true);

            if ($waitingForDocuments && $this->transitions->canTransition($from, CaseStatus::DocumentsReceived)) {
                $locked->forceFill(['status' => CaseStatus::DocumentsReceived]);
                $this->recordActivity->handle(
                    case: $locked,
                    type: CaseActivityType::StatusChanged,
                    summary: sprintf('Status changed from %s to %s', $from->label(), CaseStatus::DocumentsReceived->label()),
                    actor: $actor,
                    properties: [
                        'from' => $from->value,
                        'to' => CaseStatus::DocumentsReceived->value,
                        'note' => $actor === null ? 'Patient uploaded documents' : 'Staff added documents',
                    ],
                );
            }

            $locked->forceFill(['last_activity_at' => now()])->save();

            return $stored;
        });

        foreach ($documents as $document) {
            $this->queueDocument->handle($document);
        }

        return $documents;
    }
}
