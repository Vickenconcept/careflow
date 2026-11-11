<?php

namespace App\Actions\Patients;

use App\Domains\Cases\CaseStatusTransitionMap;
use App\Enums\CaseActivityType;
use App\Enums\CaseStatus;
use App\Enums\CommunicationChannel;
use App\Enums\CommunicationDirection;
use App\Enums\CommunicationStatus;
use App\Enums\DocumentCategory;
use App\Enums\DocumentProcessingStatus;
use App\Models\CaseCommunication;
use App\Models\PatientCase;
use App\Models\PatientDocument;
use App\Models\User;
use App\Notifications\Patients\PatientPortalUpdateStaffNotification;
use App\Support\PatientDocumentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Records what a patient sends through the secure tracking page.
 * Patients are not users, so staff-facing side effects use the case's own staff.
 */
class RecordPatientPortalSubmission
{
    public function __construct(
        private PatientDocumentStorage $documentStorage,
        private RecordCaseActivity $recordActivity,
        private QueuePatientDocumentForProcessing $queueDocument,
        private CreateCaseTask $createTask,
        private CaseStatusTransitionMap $transitions,
    ) {}

    /**
     * @param  list<array{file: UploadedFile, category: DocumentCategory}>  $files
     * @return list<PatientDocument>
     */
    public function uploadDocuments(PatientCase $case, array $files): array
    {
        $documents = DB::transaction(function () use ($case, $files) {
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
                summary: sprintf('Patient uploaded %d additional document(s) through the portal', count($stored)),
                properties: ['document_uuids' => array_map(fn (PatientDocument $document) => $document->uuid, $stored)],
                patientVisible: true,
            );

            $from = $locked->status;
            $awaiting = in_array($from, [CaseStatus::AwaitingDocuments, CaseStatus::AdditionalInformationRequired], true);

            if ($awaiting && $this->transitions->canTransition($from, CaseStatus::DocumentsReceived)) {
                $locked->forceFill(['status' => CaseStatus::DocumentsReceived]);
                $this->recordActivity->handle(
                    case: $locked,
                    type: CaseActivityType::StatusChanged,
                    summary: sprintf('Status changed from %s to %s', $from->label(), CaseStatus::DocumentsReceived->label()),
                    properties: ['from' => $from->value, 'to' => CaseStatus::DocumentsReceived->value, 'note' => 'Patient uploaded documents'],
                    patientVisible: false,
                );
            }

            $locked->forceFill(['last_activity_at' => now()])->save();

            return $stored;
        });

        foreach ($documents as $document) {
            $this->queueDocument->handle($document);
        }

        $this->alertStaff($case, 'New documents from the patient', 'documents');

        return $documents;
    }

    public function reply(PatientCase $case, string $body): CaseCommunication
    {
        $case->loadMissing('patient');

        $communication = DB::transaction(function () use ($case, $body) {
            $communication = CaseCommunication::query()->create([
                'team_id' => $case->team_id,
                'patient_case_id' => $case->id,
                'uuid' => (string) Str::uuid(),
                'channel' => CommunicationChannel::PatientPortal,
                'direction' => CommunicationDirection::Inbound,
                'status' => CommunicationStatus::Logged,
                'language' => $case->patient->preferred_language ?: 'en',
                'body' => $body,
                'sender' => $case->patient->fullName(),
                'is_ai_draft' => false,
                'requires_approval' => false,
                'is_demo' => $case->is_demo,
            ]);

            $case->forceFill(['last_activity_at' => now()])->save();

            $this->recordActivity->handle(
                case: $case,
                type: CaseActivityType::CommunicationLogged,
                summary: 'Patient sent a message through the portal',
                properties: ['communication_uuid' => $communication->uuid],
                patientVisible: true,
            );

            return $communication;
        });

        $this->alertStaff($case, 'Patient replied through the portal', 'message');

        return $communication;
    }

    private function alertStaff(PatientCase $case, string $title, string $kind): void
    {
        $case->loadMissing(['team', 'assignedCoordinator']);

        $owner = $case->assignedCoordinator ?? $case->team->members()->orderBy('users.id')->first();

        if ($owner instanceof User) {
            $this->createTask->handle($case, $owner, [
                'title' => $title,
                'description' => 'Sent by the patient through the secure tracking page. Please review and respond.',
                'assigned_user_id' => $case->assignedCoordinator?->id,
                'priority' => 'normal',
            ]);
        }

        $recipients = $case->assignedCoordinator !== null
            ? collect([$case->assignedCoordinator])
            : $case->team->members()->wherePivotIn('role', ['owner', 'admin', 'coordinator'])->get();

        if ($case->team->notificationEnabled('staff_patient_update')) {
            Notification::send($recipients, new PatientPortalUpdateStaffNotification($case, $title, $kind));
        }
    }
}
