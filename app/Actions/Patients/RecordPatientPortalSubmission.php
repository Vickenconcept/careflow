<?php

namespace App\Actions\Patients;

use App\Domains\Cases\CaseStatusTransitionMap;
use App\Enums\CaseActivityType;
use App\Enums\CommunicationChannel;
use App\Enums\CommunicationDirection;
use App\Enums\CommunicationStatus;
use App\Enums\DocumentCategory;
use App\Enums\TreatmentProposalPatientResponse;
use App\Models\CaseCommunication;
use App\Models\PatientCase;
use App\Models\PatientDocument;
use App\Models\TreatmentProposal;
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
        private RecordTreatmentProposalPatientResponse $recordProposalResponse,
        private AddCaseDocuments $addDocuments,
    ) {}

    /**
     * @param  list<array{file: UploadedFile, category: DocumentCategory}>  $files
     * @return list<PatientDocument>
     */
    public function uploadDocuments(PatientCase $case, array $files): array
    {
        $documents = $this->addDocuments->handle($case, $files);

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

    /**
     * The patient accepts or declines the proposal sent to them.
     */
    public function respondToProposal(PatientCase $case, TreatmentProposal $proposal, TreatmentProposalPatientResponse $response, ?string $notes): TreatmentProposal
    {
        $proposal = $this->recordProposalResponse->handle($proposal, null, $response, $notes);

        $this->alertStaff(
            $case,
            $response === TreatmentProposalPatientResponse::Accepted
                ? sprintf('Patient accepted proposal %s', $proposal->reference)
                : sprintf('Patient declined proposal %s', $proposal->reference),
            'proposal',
        );

        return $proposal;
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
