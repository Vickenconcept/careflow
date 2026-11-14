<?php

namespace App\Actions\Patients;

use App\Data\Patients\IntakeSubmissionData;
use App\Enums\CaseActivityType;
use App\Enums\CasePriority;
use App\Enums\CaseStatus;
use App\Enums\ConsentType;
use App\Enums\DocumentProcessingStatus;
use App\Events\Patients\PatientEnquirySubmitted;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\PatientConsent;
use App\Models\PatientDocument;
use App\Models\PatientEnquiry;
use App\Support\CaseReferenceGenerator;
use App\Support\PatientDocumentStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SubmitPatientIntake
{
    public function __construct(
        private CaseReferenceGenerator $caseReferences,
        private PatientDocumentStorage $documentStorage,
        private RecordCaseActivity $recordActivity,
    ) {}

    /**
     * Persist a complete intake submission inside a single transaction.
     *
     * @return array{patient: Patient, enquiry: PatientEnquiry, case: PatientCase, portal_token: string}
     */
    public function handle(IntakeSubmissionData $data): array
    {
        if (! $data->enquiryConsentAccepted || ! $data->healthDataConsentAccepted) {
            throw new RuntimeException('Required consents were not accepted.');
        }

        return DB::transaction(function () use ($data) {
            $submissionUuid = (string) Str::uuid();
            $submittedAt = now();

            $patient = Patient::query()->firstOrNew([
                'team_id' => $data->team->id,
                'email' => $data->email,
            ]);

            $patient->fill([
                'uuid' => $patient->uuid ?: (string) Str::uuid(),
                'first_name' => $data->firstName,
                'last_name' => $data->lastName,
                'date_of_birth' => $data->dateOfBirth,
                'gender' => $data->gender,
                'country_of_residence' => $data->countryOfResidence,
                'phone' => $data->phone,
                'preferred_language' => $data->preferredLanguage,
            ]);
            $patient->save();

            $enquiry = PatientEnquiry::query()->create([
                'team_id' => $data->team->id,
                'patient_id' => $patient->id,
                'department_id' => $data->departmentId,
                'submission_uuid' => $submissionUuid,
                'preferred_language' => $data->preferredLanguage,
                'reason_for_contact' => $data->reasonForContact,
                'diagnosis' => $data->diagnosis,
                'symptoms_or_history' => $data->symptomsOrHistory,
                'previous_procedures' => $data->previousProcedures,
                'current_medications' => $data->currentMedications,
                'known_allergies' => $data->knownAllergies,
                'additional_comments' => $data->additionalComments,
                'source_ip' => $data->sourceIp,
                'user_agent' => $data->userAgent,
                'submitted_at' => $submittedAt,
            ]);

            $case = PatientCase::query()->create([
                'team_id' => $data->team->id,
                'patient_id' => $patient->id,
                'patient_enquiry_id' => $enquiry->id,
                'department_id' => $data->departmentId,
                'reference' => $this->caseReferences->next($data->team),
                'status' => count($data->documents) > 0
                    ? CaseStatus::DocumentsReceived
                    : CaseStatus::NewEnquiry,
                'priority' => CasePriority::Normal,
                'last_activity_at' => $submittedAt,
            ]);

            $this->storeConsents($data, $patient, $enquiry, $submissionUuid, $submittedAt);

            $this->recordActivity->handle(
                case: $case,
                type: CaseActivityType::CaseCreated,
                summary: $data->recordedBy === null
                    ? 'Patient enquiry submitted through the public intake portal'
                    : sprintf('Enquiry entered by %s', $data->recordedBy->name),
                actor: $data->recordedBy,
                properties: [
                    'submission_uuid' => $submissionUuid,
                    'preferred_language' => $data->preferredLanguage,
                    'document_count' => count($data->documents),
                ],
                patientVisible: true,
            );

            foreach ($data->documents as $documentPayload) {
                $stored = $this->documentStorage->store(
                    $data->team,
                    $case,
                    $documentPayload['file'],
                );

                PatientDocument::query()->create([
                    'team_id' => $data->team->id,
                    'patient_id' => $patient->id,
                    'patient_case_id' => $case->id,
                    'uuid' => $stored['uuid'],
                    'original_filename' => $stored['original_filename'],
                    'disk' => $stored['disk'],
                    'path' => $stored['path'],
                    'mime_type' => $stored['mime_type'],
                    'extension' => $stored['extension'],
                    'size_bytes' => $stored['size_bytes'],
                    'checksum_sha256' => $stored['checksum_sha256'],
                    'category' => $documentPayload['category'],
                    'processing_status' => DocumentProcessingStatus::Uploaded,
                    'uploaded_at' => $submittedAt,
                ]);

                $this->recordActivity->handle(
                    case: $case,
                    type: CaseActivityType::DocumentUploaded,
                    summary: sprintf('Document uploaded: %s', $stored['original_filename']),
                    properties: [
                        'document_uuid' => $stored['uuid'],
                        'category' => $documentPayload['category']->value,
                    ],
                );
            }

            $portalToken = $case->issuePortalAccessToken();

            DB::afterCommit(function () use ($enquiry, $case, $portalToken, $data): void {
                event(new PatientEnquirySubmitted($enquiry->id, $case->id, $data->emailPatient ? $portalToken : ''));
            });

            return [
                'patient' => $patient->fresh(),
                'enquiry' => $enquiry->fresh(),
                'case' => $case->fresh(),
                'portal_token' => $portalToken,
            ];
        });
    }

    private function storeConsents(
        IntakeSubmissionData $data,
        Patient $patient,
        PatientEnquiry $enquiry,
        string $submissionUuid,
        mixed $submittedAt,
    ): void {
        $privacyVersion = (string) config('careflow.consent.privacy_notice_version');

        $consentRecords = [
            [
                'type' => ConsentType::Enquiry,
                'version' => (string) config('careflow.consent.enquiry_consent_version'),
                'accepted' => $data->enquiryConsentAccepted,
            ],
            [
                'type' => ConsentType::HealthData,
                'version' => (string) config('careflow.consent.health_data_consent_version'),
                'accepted' => $data->healthDataConsentAccepted,
            ],
        ];

        if ($data->aiProcessingConsentAccepted) {
            $consentRecords[] = [
                'type' => ConsentType::AiProcessing,
                'version' => (string) config('careflow.consent.ai_processing_consent_version'),
                'accepted' => true,
            ];
        }

        foreach ($consentRecords as $record) {
            PatientConsent::query()->create([
                'team_id' => $data->team->id,
                'patient_id' => $patient->id,
                'patient_enquiry_id' => $enquiry->id,
                'submission_uuid' => $submissionUuid,
                'consent_type' => $record['type'],
                'privacy_notice_version' => $privacyVersion,
                'consent_text_version' => $record['version'],
                'accepted' => $record['accepted'],
                'consented_at' => $submittedAt,
                'source_ip' => $data->sourceIp,
            ]);
        }
    }
}
