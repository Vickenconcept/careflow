<?php

namespace App\Demo;

use App\Domains\Documents\Data\StructuredExtractionResult;
use App\Enums\AppointmentConfirmationSource;
use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Enums\CaseActivityType;
use App\Enums\CasePriority;
use App\Enums\CaseStatus;
use App\Enums\CaseTaskPriority;
use App\Enums\CaseTaskStatus;
use App\Enums\ClinicalApprovalOutcome;
use App\Enums\ClinicalRecommendationStatus;
use App\Enums\ConsentType;
use App\Enums\DoctorReviewOutcome;
use App\Enums\DoctorReviewStatus;
use App\Enums\DocumentCategory;
use App\Enums\DocumentProcessingStatus;
use App\Enums\FollowUpStatus;
use App\Enums\FollowUpType;
use App\Enums\PatientGender;
use App\Enums\TeamRole;
use App\Enums\TravelArrangementStatus;
use App\Enums\TreatmentProposalStatus;
use App\Enums\VisaAssistanceStatus;
use App\Models\CaseActivity;
use App\Models\CaseAppointment;
use App\Models\CaseTask;
use App\Models\ClinicalApproval;
use App\Models\ClinicalObservation;
use App\Models\ClinicalRecommendation;
use App\Models\Department;
use App\Models\DoctorReview;
use App\Models\DocumentExtractedText;
use App\Models\DocumentExtraction;
use App\Models\DocumentProcessingRun;
use App\Models\DocumentSummary;
use App\Models\DocumentTranslation;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\PatientConsent;
use App\Models\PatientDocument;
use App\Models\PatientEnquiry;
use App\Models\PatientFollowUp;
use App\Models\Team;
use App\Models\TravelArrangement;
use App\Models\TreatmentProposal;
use App\Models\User;
use App\Support\CaseReferenceGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Idempotent synthetic demonstration dataset for CareFlow International Medical Centre.
 *
 * All patient content is fictional and labelled as demo data.
 */
class CareFlowDemoDataset
{
    public const TEAM_SLUG = 'careflow-international';

    public const PASSWORD = 'password';

    /**
     * @return array{
     *     team: Team,
     *     users: array<string, User>,
     *     departments: list<Department>,
     *     cases_created: int
     * }
     */
    public function seed(): array
    {
        return DB::transaction(function () {
            $users = $this->seedStaff();
            $team = $this->seedOrganization($users['admin']);
            $departments = $this->seedDepartments($team);
            $this->attachStaff($team, $users);
            $this->purgeExistingDemoPatients($team);
            $casesCreated = $this->seedCases($team, $departments, $users);

            return [
                'team' => $team->fresh(),
                'users' => $users,
                'departments' => $departments,
                'cases_created' => $casesCreated,
            ];
        });
    }

    /**
     * @return array{admin: User, coordinator: User, doctor: User, nurse: User, viewer: User}
     */
    protected function seedStaff(): array
    {
        return [
            'admin' => $this->user('Demo Hospital Admin', 'admin@careflow-demo.test'),
            'coordinator' => $this->user('Demo Coordinator Leyla', 'coordinator@careflow-demo.test'),
            'doctor' => $this->user('Demo Doctor Emre', 'doctor@careflow-demo.test'),
            'nurse' => $this->user('Demo Nurse Sara', 'nurse@careflow-demo.test'),
            'viewer' => $this->user('Demo Management Viewer', 'viewer@careflow-demo.test'),
        ];
    }

    protected function user(string $name, string $email): User
    {
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => now(),
            ],
        );

        if ($user->wasRecentlyCreated === false) {
            $user->forceFill([
                'name' => $name,
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => now(),
            ])->save();
        }

        return $user;
    }

    protected function seedOrganization(User $admin): Team
    {
        $team = Team::query()->firstOrCreate(
            ['slug' => self::TEAM_SLUG],
            [
                'name' => 'CareFlow International Medical Centre',
                'is_personal' => false,
            ],
        );

        $team->ensureOrganizationSetting()->update([
            'legal_name' => 'CareFlow International Medical Centre',
            'contact_email' => 'international@careflow-demo.test',
            'contact_phone' => '+90 392 000 0000',
            'address_line_1' => '1 Demonstration Avenue',
            'city' => 'Nicosia',
            'country' => 'CY',
            'timezone' => 'Asia/Nicosia',
            'default_currency' => 'EUR',
            'default_language' => 'en',
            'supported_languages' => ['en', 'tr', 'ar', 'ru', 'az'],
            'brand_primary_color' => '#5F7D45',
            'brand_secondary_color' => '#134E4A',
            'ai_processing_enabled' => true,
            'ai_provider' => 'openai',
            'enabled_modules' => ['intake', 'crm', 'documents', 'clinical'],
        ]);

        if (! $team->members()->where('user_id', $admin->id)->exists()) {
            $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
        } else {
            $team->members()->updateExistingPivot($admin->id, ['role' => TeamRole::Admin->value]);
        }

        $admin->switchTeam($team);

        return $team;
    }

    /**
     * @return list<Department>
     */
    protected function seedDepartments(Team $team): array
    {
        $definitions = [
            ['name' => 'Cardiology', 'code' => 'CARD'],
            ['name' => 'Orthopedics', 'code' => 'ORTHO'],
            ['name' => 'IVF & Fertility', 'code' => 'IVF'],
            ['name' => 'Oncology', 'code' => 'ONC'],
            ['name' => 'Plastic Surgery', 'code' => 'PLAST'],
        ];

        $departments = [];

        foreach ($definitions as $index => $definition) {
            $departments[] = Department::query()->updateOrCreate(
                [
                    'team_id' => $team->id,
                    'slug' => Str::slug($definition['name']),
                ],
                [
                    'name' => $definition['name'],
                    'code' => $definition['code'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                    'description' => 'Demonstration demonstration department. Not a real clinical service.',
                ],
            );
        }

        return $departments;
    }

    /**
     * @param  array{admin: User, coordinator: User, doctor: User, nurse: User, viewer: User}  $users
     */
    protected function attachStaff(Team $team, array $users): void
    {
        $roles = [
            'coordinator' => TeamRole::Coordinator,
            'doctor' => TeamRole::Doctor,
            'nurse' => TeamRole::Nurse,
            'viewer' => TeamRole::ManagementViewer,
        ];

        foreach ($roles as $key => $role) {
            $user = $users[$key];

            if (! $team->members()->where('user_id', $user->id)->exists()) {
                $team->members()->attach($user, ['role' => $role->value]);
            } else {
                $team->members()->updateExistingPivot($user->id, ['role' => $role->value]);
            }

            $user->switchTeam($team);
        }
    }

    protected function purgeExistingDemoPatients(Team $team): void
    {
        $patientIds = Patient::query()
            ->where('team_id', $team->id)
            ->where('is_demo', true)
            ->pluck('id');

        if ($patientIds->isEmpty()) {
            return;
        }

        $caseIds = PatientCase::query()
            ->where('team_id', $team->id)
            ->whereIn('patient_id', $patientIds)
            ->pluck('id');

        $documentIds = PatientDocument::withTrashed()
            ->where('team_id', $team->id)
            ->whereIn('patient_id', $patientIds)
            ->pluck('id');

        if ($documentIds->isNotEmpty()) {
            PatientDocument::withTrashed()
                ->whereIn('id', $documentIds)
                ->get()
                ->each(function (PatientDocument $document): void {
                    Storage::disk($document->disk)->delete($document->path);
                });

            $recommendationIds = ClinicalRecommendation::query()
                ->whereIn('patient_case_id', $caseIds)
                ->pluck('id');

            ClinicalApproval::query()
                ->where('team_id', $team->id)
                ->whereIn('clinical_recommendation_id', $recommendationIds)
                ->delete();

            ClinicalRecommendation::query()->whereIn('patient_case_id', $caseIds)->delete();
            ClinicalObservation::query()->whereIn('patient_case_id', $caseIds)->delete();
            DoctorReview::query()->whereIn('patient_case_id', $caseIds)->delete();

            DocumentSummary::query()->whereIn('patient_document_id', $documentIds)->delete();
            DocumentTranslation::query()->whereIn('patient_document_id', $documentIds)->delete();
            DocumentExtraction::query()->whereIn('patient_document_id', $documentIds)->delete();
            DocumentExtractedText::query()->whereIn('patient_document_id', $documentIds)->delete();
            DocumentProcessingRun::query()->whereIn('patient_document_id', $documentIds)->delete();
            PatientDocument::withTrashed()->whereIn('id', $documentIds)->forceDelete();
        }

        CaseActivity::query()->whereIn('patient_case_id', $caseIds)->delete();
        PatientConsent::query()->whereIn('patient_id', $patientIds)->delete();
        PatientCase::withTrashed()->whereIn('id', $caseIds)->forceDelete();
        PatientEnquiry::query()->whereIn('patient_id', $patientIds)->delete();
        Patient::withTrashed()->whereIn('id', $patientIds)->forceDelete();
    }

    /**
     * @param  list<Department>  $departments
     * @param  array{admin: User, coordinator: User, doctor: User, nurse: User, viewer: User}  $users
     */
    protected function seedCases(Team $team, array $departments, array $users): int
    {
        $generator = app(CaseReferenceGenerator::class);
        $definitions = $this->caseDefinitions($departments);
        $count = 0;

        foreach ($definitions as $definition) {
            $this->seedCase($team, $users, $generator, $definition);
            $count++;
        }

        return $count;
    }

    /**
     * @param  list<Department>  $departments
     * @return list<array<string, mixed>>
     */
    protected function caseDefinitions(array $departments): array
    {
        $byCode = collect($departments)->keyBy('code');

        return [
            $this->def('Ayla Demir', 'TR', 'tr', PatientGender::Female, $byCode['CARD'], CaseStatus::NewEnquiry, CasePriority::Normal, false, false, false),
            $this->def('Rashid Aliyev', 'AZ', 'az', PatientGender::Male, $byCode['ORTHO'], CaseStatus::AwaitingDocuments, CasePriority::Normal, false, false, false),
            $this->def('Elena Petrova', 'RU', 'ru', PatientGender::Female, $byCode['IVF'], CaseStatus::DocumentsReceived, CasePriority::High, true, false, false),
            $this->def('Omar Hassan', 'AE', 'ar', PatientGender::Male, $byCode['CARD'], CaseStatus::AiProcessing, CasePriority::Urgent, true, false, false),
            $this->def('Fatima Yilmaz', 'TR', 'tr', PatientGender::Female, $byCode['ONC'], CaseStatus::AwaitingCoordinatorReview, CasePriority::High, true, true, false),
            $this->def('James Whitaker', 'GB', 'en', PatientGender::Male, $byCode['ORTHO'], CaseStatus::AwaitingDoctorReview, CasePriority::Normal, true, true, false),
            $this->def('Mariam Nasser', 'EG', 'ar', PatientGender::Female, $byCode['PLAST'], CaseStatus::AdditionalInformationRequired, CasePriority::Normal, true, true, true),
            $this->def('Hans Mueller', 'DE', 'en', PatientGender::Male, $byCode['CARD'], CaseStatus::TreatmentProposalPreparation, CasePriority::High, true, true, true),
            $this->def('Sofia Rossi', 'IT', 'en', PatientGender::Female, $byCode['IVF'], CaseStatus::ProposalSent, CasePriority::Normal, true, true, true),
            $this->def('Kenji Tanaka', 'JP', 'en', PatientGender::Male, $byCode['ORTHO'], CaseStatus::AwaitingPatientResponse, CasePriority::Normal, true, true, true),
            $this->def('Amira Said', 'MA', 'fr', PatientGender::Female, $byCode['ONC'], CaseStatus::AppointmentPlanning, CasePriority::High, true, true, true),
            $this->def('Noah Berg', 'SE', 'en', PatientGender::Male, $byCode['CARD'], CaseStatus::TravelCoordination, CasePriority::Normal, true, true, true),
            $this->def('Leila Karimi', 'IR', 'en', PatientGender::Female, $byCode['PLAST'], CaseStatus::TreatmentScheduled, CasePriority::Normal, true, true, true),
            $this->def('Viktor Novak', 'UA', 'ru', PatientGender::Male, $byCode['ORTHO'], CaseStatus::FollowUp, CasePriority::Low, true, true, true),
            $this->def('Chloe Martin', 'FR', 'en', PatientGender::Female, $byCode['IVF'], CaseStatus::Closed, CasePriority::Normal, true, true, true),
            $this->def('Ahmed Farouk', 'SA', 'ar', PatientGender::Male, $byCode['CARD'], CaseStatus::Declined, CasePriority::Low, false, false, false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function def(
        string $fullName,
        string $country,
        string $language,
        PatientGender $gender,
        Department $department,
        CaseStatus $status,
        CasePriority $priority,
        bool $withDocuments,
        bool $withAi,
        bool $withClinical,
    ): array {
        [$first, $last] = explode(' ', $fullName, 2);

        return compact('first', 'last', 'country', 'language', 'gender', 'department', 'status', 'priority', 'withDocuments', 'withAi', 'withClinical');
    }

    /**
     * @param  array{admin: User, coordinator: User, doctor: User, nurse: User, viewer: User}  $users
     * @param  array<string, mixed>  $definition
     */
    protected function seedCase(
        Team $team,
        array $users,
        CaseReferenceGenerator $generator,
        array $definition,
    ): PatientCase {
        /** @var Department $department */
        $department = $definition['department'];
        /** @var CaseStatus $status */
        $status = $definition['status'];
        /** @var PatientGender $gender */
        $gender = $definition['gender'];

        $email = sprintf(
            '%s.%s.demo@careflow-demo.test',
            Str::slug($definition['first']),
            Str::slug($definition['last']),
        );

        $patient = Patient::query()->create([
            'team_id' => $team->id,
            'uuid' => (string) Str::uuid(),
            'first_name' => $definition['first'],
            'last_name' => $definition['last'],
            'date_of_birth' => now()->subYears(fake()->numberBetween(24, 68))->subDays(fake()->numberBetween(0, 300))->toDateString(),
            'gender' => $gender,
            'country_of_residence' => $definition['country'],
            'email' => $email,
            'phone' => '+90 392 '.fake()->numerify('### ####'),
            'preferred_language' => $definition['language'],
            'is_demo' => true,
        ]);

        $enquiry = PatientEnquiry::query()->create([
            'team_id' => $team->id,
            'patient_id' => $patient->id,
            'department_id' => $department->id,
            'submission_uuid' => (string) Str::uuid(),
            'preferred_language' => $definition['language'],
            'reason_for_contact' => sprintf(
                '[SYNTHETIC DEMO] International enquiry for %s evaluation.',
                $department->name,
            ),
            'diagnosis' => '[SYNTHETIC DEMO] Reported diagnosis placeholder — not a real clinical fact.',
            'symptoms_or_history' => '[SYNTHETIC DEMO] Fictional symptom history for demonstration only.',
            'previous_procedures' => null,
            'current_medications' => '[SYNTHETIC DEMO] None verified.',
            'known_allergies' => '[SYNTHETIC DEMO] None documented.',
            'additional_comments' => 'Demonstration dataset record. Never use as clinical guidance.',
            'source_ip' => '127.0.0.1',
            'user_agent' => 'CareFlowDemoSeeder/1.0',
            'submitted_at' => now()->subDays(fake()->numberBetween(1, 40)),
        ]);

        foreach ([ConsentType::Enquiry, ConsentType::HealthData, ConsentType::AiProcessing] as $consentType) {
            PatientConsent::query()->create([
                'team_id' => $team->id,
                'patient_id' => $patient->id,
                'patient_enquiry_id' => $enquiry->id,
                'submission_uuid' => $enquiry->submission_uuid,
                'consent_type' => $consentType,
                'privacy_notice_version' => config('careflow.consent.privacy_notice_version'),
                'consent_text_version' => match ($consentType) {
                    ConsentType::Enquiry => config('careflow.consent.enquiry_consent_version'),
                    ConsentType::HealthData => config('careflow.consent.health_data_consent_version'),
                    ConsentType::AiProcessing => config('careflow.consent.ai_processing_consent_version'),
                },
                'accepted' => true,
                'consented_at' => $enquiry->submitted_at,
                'source_ip' => '127.0.0.1',
            ]);
        }

        $assignDoctor = in_array($status, [
            CaseStatus::AwaitingDoctorReview,
            CaseStatus::AdditionalInformationRequired,
            CaseStatus::TreatmentProposalPreparation,
            CaseStatus::ProposalSent,
            CaseStatus::AwaitingPatientResponse,
            CaseStatus::AppointmentPlanning,
            CaseStatus::TravelCoordination,
            CaseStatus::TreatmentScheduled,
            CaseStatus::TreatmentCompleted,
            CaseStatus::FollowUp,
            CaseStatus::Closed,
        ], true);

        $case = PatientCase::query()->create([
            'team_id' => $team->id,
            'patient_id' => $patient->id,
            'patient_enquiry_id' => $enquiry->id,
            'department_id' => $department->id,
            'reference' => $generator->next($team),
            'status' => $status,
            'priority' => $definition['priority'],
            'assigned_coordinator_id' => $status === CaseStatus::NewEnquiry ? null : $users['coordinator']->id,
            'assigned_doctor_id' => $assignDoctor ? $users['doctor']->id : null,
            'last_activity_at' => now()->subHours(fake()->numberBetween(1, 200)),
            'is_demo' => true,
        ]);

        CaseActivity::query()->create([
            'team_id' => $team->id,
            'patient_case_id' => $case->id,
            'actor_user_id' => null,
            'activity_type' => CaseActivityType::CaseCreated,
            'summary' => 'Synthetic demo case created from patient intake',
            'properties' => ['demo' => true],
            'is_patient_visible' => true,
            'occurred_at' => $enquiry->submitted_at,
        ]);

        if ($definition['withDocuments']) {
            $document = $this->seedDocument($team, $patient, $case, $definition['withAi']);

            if ($definition['withClinical']) {
                $this->seedClinicalTrail($team, $case, $users, $document, $status);
            }
        }

        if ($case->assigned_coordinator_id !== null) {
            CaseActivity::query()->create([
                'team_id' => $team->id,
                'patient_case_id' => $case->id,
                'actor_user_id' => $users['coordinator']->id,
                'activity_type' => CaseActivityType::NoteAdded,
                'summary' => '[SYNTHETIC DEMO] Coordinator first response recorded.',
                'properties' => ['demo' => true],
                'is_patient_visible' => false,
                'occurred_at' => ($enquiry->submitted_at ?? now())->copy()->addHours(4),
            ]);
        }

        $this->seedOperationalTrail($team, $case, $users, $status);

        return $case;
    }

    protected function seedDocument(
        Team $team,
        Patient $patient,
        PatientCase $case,
        bool $withAi,
    ): PatientDocument {
        $uuid = (string) Str::uuid();
        $path = "teams/{$team->id}/cases/{$case->id}/documents/{$uuid}.txt";
        $contents = implode("\n", [
            '[SYNTHETIC DEMO DOCUMENT]',
            'This file is fictional demonstration content for CareFlow.',
            'It must never be treated as a real patient record.',
            'Patient: '.$patient->fullName(),
            'Case: '.$case->reference,
            'Reported diagnosis: Demo arrhythmia suspicion',
            'Patient complaint: palpitations during demonstration scenario',
        ]);

        Storage::disk('local')->put($path, $contents);

        $document = PatientDocument::query()->create([
            'team_id' => $team->id,
            'patient_id' => $patient->id,
            'patient_case_id' => $case->id,
            'uuid' => $uuid,
            'original_filename' => 'synthetic-'.$case->reference.'.txt',
            'disk' => 'local',
            'path' => $path,
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'size_bytes' => strlen($contents),
            'checksum_sha256' => hash('sha256', $contents),
            'category' => DocumentCategory::MedicalReport,
            'processing_status' => $withAi
                ? DocumentProcessingStatus::NeedsReview
                : DocumentProcessingStatus::Uploaded,
            'original_language' => $withAi ? 'en' : null,
            'uploaded_at' => now()->subDays(1),
        ]);

        CaseActivity::query()->create([
            'team_id' => $team->id,
            'patient_case_id' => $case->id,
            'actor_user_id' => null,
            'activity_type' => CaseActivityType::DocumentUploaded,
            'summary' => 'Synthetic demo document uploaded',
            'properties' => ['document_uuid' => $document->uuid, 'demo' => true],
            'is_patient_visible' => false,
            'occurred_at' => $document->uploaded_at,
        ]);

        if ($withAi) {
            $this->seedAiOutputs($team, $document, $contents);
        }

        return $document;
    }

    protected function seedAiOutputs(Team $team, PatientDocument $document, string $text): void
    {
        $run = DocumentProcessingRun::query()->create([
            'team_id' => $team->id,
            'patient_document_id' => $document->id,
            'uuid' => (string) Str::uuid(),
            'provider' => 'mock',
            'status' => DocumentProcessingStatus::NeedsReview,
            'attempt' => 1,
            'started_at' => now()->subHour(),
            'finished_at' => now()->subMinutes(50),
            'usage' => ['provider' => 'mock', 'synthetic' => true],
        ]);

        $payload = StructuredExtractionResult::emptySchema();
        $payload['document_type'] = DocumentCategory::MedicalReport->value;
        $payload['original_language'] = 'en';
        $payload['uncertain_sections'] = [[
            'field' => 'all_clinical_fields',
            'reason' => 'Mock provider does not invent clinical facts from documents.',
        ]];
        $payload['diagnoses_as_documented'] = [[
            'value' => 'Demo arrhythmia suspicion',
            'source_reference' => 'synthetic_demo_text',
            'confidence' => 0.4,
            'review_status' => 'unreviewed',
        ]];
        $payload['missing_or_unreadable_information'] = [
            'No verified laboratory values extracted.',
        ];

        DocumentExtractedText::query()->create([
            'team_id' => $team->id,
            'patient_document_id' => $document->id,
            'document_processing_run_id' => $run->id,
            'version' => 1,
            'extracted_text' => "[SYNTHETIC DEMO WRAPPER]\n{$text}\n[END SYNTHETIC DEMO WRAPPER]",
            'provider' => 'mock',
            'is_synthetic' => true,
            'warnings' => ['Mock text extractor in use. Output is synthetic demonstration content.'],
            'is_current' => true,
        ]);

        DocumentExtraction::query()->create([
            'team_id' => $team->id,
            'patient_document_id' => $document->id,
            'document_processing_run_id' => $run->id,
            'version' => 1,
            'schema_version' => '2026-11-01',
            'payload' => $payload,
            'provider' => 'mock',
            'is_synthetic' => true,
            'review_status' => 'unreviewed',
            'is_current' => true,
        ]);

        DocumentTranslation::query()->create([
            'team_id' => $team->id,
            'patient_document_id' => $document->id,
            'document_processing_run_id' => $run->id,
            'version' => 1,
            'source_language' => 'en',
            'target_language' => 'en',
            'translated_text' => $text,
            'provider' => 'mock',
            'status' => 'completed',
            'disclaimer' => 'AI-assisted translation. Not a certified medical translation.',
            'is_synthetic' => true,
            'is_current' => true,
            'translated_at' => now()->subMinutes(50),
        ]);

        DocumentSummary::query()->create([
            'team_id' => $team->id,
            'patient_document_id' => $document->id,
            'document_processing_run_id' => $run->id,
            'version' => 1,
            'sections' => [
                'document_overview' => 'Mock AI summary for '.$document->original_filename.'. Demonstration only.',
                'reported_diagnosis' => $payload['diagnoses_as_documented'],
                'uncertain_or_ambiguous_content' => $payload['uncertain_sections'],
                'missing_or_unclear_information' => $payload['missing_or_unreadable_information'],
            ],
            'provider' => 'mock',
            'disclaimer' => 'AI-generated draft. Requires review by an authorized healthcare professional.',
            'is_synthetic' => true,
            'review_status' => 'unreviewed',
            'is_current' => true,
        ]);
    }

    /**
     * @param  array{admin: User, coordinator: User, doctor: User, nurse: User, viewer: User}  $users
     */
    protected function seedClinicalTrail(
        Team $team,
        PatientCase $case,
        array $users,
        PatientDocument $document,
        CaseStatus $status,
    ): void {
        $completed = ! in_array($status, [
            CaseStatus::AwaitingDoctorReview,
        ], true);

        $outcome = match ($status) {
            CaseStatus::AdditionalInformationRequired => DoctorReviewOutcome::NeedMoreInformation,
            default => DoctorReviewOutcome::ReadyForProposal,
        };

        $review = DoctorReview::query()->create([
            'team_id' => $team->id,
            'patient_case_id' => $case->id,
            'reviewer_user_id' => $users['doctor']->id,
            'uuid' => (string) Str::uuid(),
            'status' => $completed
                ? ($outcome === DoctorReviewOutcome::NeedMoreInformation
                    ? DoctorReviewStatus::AdditionalInfoRequested
                    : DoctorReviewStatus::Completed)
                : DoctorReviewStatus::InProgress,
            'findings' => $completed
                ? '[SYNTHETIC DEMO] Human-reviewed evaluation. Not a real clinical opinion.'
                : null,
            'additional_information_request' => $outcome === DoctorReviewOutcome::NeedMoreInformation
                ? '[SYNTHETIC DEMO] Please upload recent laboratory results.'
                : null,
            'outcome' => $completed ? $outcome : null,
            'started_at' => now()->subDays(2),
            'completed_at' => $completed ? now()->subDay() : null,
            'is_demo' => true,
        ]);

        ClinicalObservation::query()->create([
            'team_id' => $team->id,
            'patient_case_id' => $case->id,
            'doctor_review_id' => $review->id,
            'author_user_id' => $users['doctor']->id,
            'body' => '[SYNTHETIC DEMO] Clinical observation recorded during demonstration review.',
            'visibility' => 'coordinator_shared',
            'observed_at' => now()->subDays(2),
        ]);

        if (! $completed || $outcome === DoctorReviewOutcome::NeedMoreInformation) {
            return;
        }

        $recommendation = ClinicalRecommendation::query()->create([
            'team_id' => $team->id,
            'patient_case_id' => $case->id,
            'doctor_review_id' => $review->id,
            'author_user_id' => $users['doctor']->id,
            'uuid' => (string) Str::uuid(),
            'version' => 1,
            'status' => ClinicalRecommendationStatus::Approved,
            'recommendation_text' => '[SYNTHETIC DEMO] Human-authored recommendation for demonstration coordination only.',
            'supporting_document_uuids' => [$document->uuid],
            'is_ai_generated' => false,
            'is_current' => true,
        ]);

        ClinicalApproval::query()->create([
            'team_id' => $team->id,
            'clinical_recommendation_id' => $recommendation->id,
            'approver_user_id' => $users['doctor']->id,
            'outcome' => ClinicalApprovalOutcome::Approved,
            'notes' => '[SYNTHETIC DEMO] Explicit human clinical approval for demo workflow.',
            'decided_at' => now()->subDay(),
        ]);
    }

    /**
     * @param  array{admin: User, coordinator: User, doctor: User, nurse: User, viewer: User}  $users
     */
    protected function seedOperationalTrail(
        Team $team,
        PatientCase $case,
        array $users,
        CaseStatus $status,
    ): void {
        $proposalStatuses = [
            CaseStatus::TreatmentProposalPreparation,
            CaseStatus::ProposalSent,
            CaseStatus::AwaitingPatientResponse,
            CaseStatus::AppointmentPlanning,
            CaseStatus::TravelCoordination,
            CaseStatus::TreatmentScheduled,
            CaseStatus::TreatmentCompleted,
            CaseStatus::FollowUp,
            CaseStatus::Closed,
        ];

        if (! in_array($status, $proposalStatuses, true)) {
            return;
        }

        $proposalStatus = match ($status) {
            CaseStatus::TreatmentProposalPreparation => TreatmentProposalStatus::Draft,
            CaseStatus::ProposalSent, CaseStatus::AwaitingPatientResponse => TreatmentProposalStatus::SentToPatient,
            default => TreatmentProposalStatus::PatientAccepted,
        };

        TreatmentProposal::query()->create([
            'team_id' => $team->id,
            'patient_case_id' => $case->id,
            'department_id' => $case->department_id,
            'responsible_physician_id' => $users['doctor']->id,
            'clinical_recommendation_id' => null,
            'created_by_user_id' => $users['coordinator']->id,
            'parent_proposal_id' => null,
            'uuid' => (string) Str::uuid(),
            'reference' => sprintf('TP-DEMO-%s', $case->reference),
            'version' => 1,
            'status' => $proposalStatus,
            'proposed_procedure' => '[SYNTHETIC DEMO] Coordinated evaluation package',
            'estimated_cost' => 4200,
            'currency' => 'EUR',
            'expected_duration' => '5 days',
            'required_appointments' => 2,
            'additional_information' => '[SYNTHETIC DEMO] Proposal content for demonstration only.',
            'valid_until' => now()->addDays(30)->toDateString(),
            'sent_at' => in_array($proposalStatus, [
                TreatmentProposalStatus::SentToPatient,
                TreatmentProposalStatus::PatientAccepted,
            ], true) ? now()->subDays(5) : null,
            'patient_responded_at' => $proposalStatus === TreatmentProposalStatus::PatientAccepted
                ? now()->subDays(3)
                : null,
            'is_current' => true,
            'is_demo' => true,
        ]);

        CaseTask::query()->create([
            'team_id' => $team->id,
            'patient_case_id' => $case->id,
            'assigned_user_id' => $users['coordinator']->id,
            'created_by_user_id' => $users['coordinator']->id,
            'uuid' => (string) Str::uuid(),
            'title' => 'Confirm patient logistics',
            'description' => '[SYNTHETIC DEMO] Administrative task for demonstration.',
            'status' => in_array($status, [CaseStatus::Closed, CaseStatus::FollowUp], true)
                ? CaseTaskStatus::Completed
                : CaseTaskStatus::Open,
            'priority' => CaseTaskPriority::Normal,
            'due_at' => now()->addDays(2),
            'completed_at' => in_array($status, [CaseStatus::Closed, CaseStatus::FollowUp], true)
                ? now()->subDay()
                : null,
            'completed_by_user_id' => in_array($status, [CaseStatus::Closed, CaseStatus::FollowUp], true)
                ? $users['coordinator']->id
                : null,
            'is_demo' => true,
        ]);

        $appointmentStatuses = [
            CaseStatus::AppointmentPlanning,
            CaseStatus::TravelCoordination,
            CaseStatus::TreatmentScheduled,
            CaseStatus::TreatmentCompleted,
            CaseStatus::FollowUp,
            CaseStatus::Closed,
        ];

        if (! in_array($status, $appointmentStatuses, true)) {
            return;
        }

        $appointmentStatus = match ($status) {
            CaseStatus::AppointmentPlanning => AppointmentStatus::Proposed,
            CaseStatus::TravelCoordination => AppointmentStatus::AwaitingConfirmation,
            CaseStatus::TreatmentScheduled => AppointmentStatus::Confirmed,
            default => AppointmentStatus::Completed,
        };

        $startsAt = now()->addDays(7)->setTime(10, 0);

        CaseAppointment::query()->create([
            'team_id' => $team->id,
            'patient_case_id' => $case->id,
            'department_id' => $case->department_id,
            'doctor_id' => $users['doctor']->id,
            'created_by_user_id' => $users['coordinator']->id,
            'confirmed_by_user_id' => in_array($appointmentStatus, [
                AppointmentStatus::Confirmed,
                AppointmentStatus::Completed,
            ], true) ? $users['coordinator']->id : null,
            'uuid' => (string) Str::uuid(),
            'status' => $appointmentStatus,
            'appointment_type' => AppointmentType::Consultation,
            'proposed_starts_at' => $startsAt,
            'proposed_ends_at' => $startsAt->copy()->addHour(),
            'confirmed_starts_at' => in_array($appointmentStatus, [
                AppointmentStatus::Confirmed,
                AppointmentStatus::Completed,
            ], true) ? $startsAt : null,
            'confirmed_ends_at' => in_array($appointmentStatus, [
                AppointmentStatus::Confirmed,
                AppointmentStatus::Completed,
            ], true) ? $startsAt->copy()->addHour() : null,
            'location' => 'International Patient Clinic',
            'meeting_instructions' => null,
            'external_his_reference' => null,
            'confirmation_source' => in_array($appointmentStatus, [
                AppointmentStatus::Confirmed,
                AppointmentStatus::Completed,
            ], true) ? AppointmentConfirmationSource::ManualHospitalVerified : null,
            'hospital_slot_verified' => in_array($appointmentStatus, [
                AppointmentStatus::Confirmed,
                AppointmentStatus::Completed,
            ], true),
            'confirmed_at' => in_array($appointmentStatus, [
                AppointmentStatus::Confirmed,
                AppointmentStatus::Completed,
            ], true) ? now()->subDays(2) : null,
            'notes' => '[SYNTHETIC DEMO] Appointment coordination placeholder.',
            'is_demo' => true,
        ]);

        $travelStatuses = [
            CaseStatus::TravelCoordination,
            CaseStatus::TreatmentScheduled,
            CaseStatus::TreatmentCompleted,
            CaseStatus::FollowUp,
            CaseStatus::Closed,
        ];

        if (in_array($status, $travelStatuses, true)) {
            TravelArrangement::query()->create([
                'team_id' => $team->id,
                'patient_case_id' => $case->id,
                'assigned_coordinator_id' => $users['coordinator']->id,
                'updated_by_user_id' => $users['coordinator']->id,
                'uuid' => (string) Str::uuid(),
                'status' => match ($status) {
                    CaseStatus::TravelCoordination => TravelArrangementStatus::InProgress,
                    CaseStatus::TreatmentScheduled => TravelArrangementStatus::Confirmed,
                    default => TravelArrangementStatus::Completed,
                },
                'expected_arrival_date' => now()->addDays(6)->toDateString(),
                'expected_departure_date' => now()->addDays(12)->toDateString(),
                'flight_information' => '[SYNTHETIC DEMO] Flight TBA — no passport data stored.',
                'airport_pickup_required' => true,
                'airport_pickup_details' => '[SYNTHETIC DEMO] Meet at arrivals hall B.',
                'transfer_arrangements' => '[SYNTHETIC DEMO] Private transfer booked.',
                'accommodation' => 'Partner hotel near campus',
                'hotel_details' => '[SYNTHETIC DEMO] Confirmation pending.',
                'visa_assistance_status' => VisaAssistanceStatus::Pending,
                'special_administrative_requirements' => null,
                'checklist' => [
                    ['key' => 'arrival_confirmed', 'label' => 'Arrival date confirmed', 'done' => true],
                    ['key' => 'transfer_booked', 'label' => 'Transfer arranged', 'done' => $status !== CaseStatus::TravelCoordination],
                    ['key' => 'accommodation_booked', 'label' => 'Accommodation arranged', 'done' => false],
                ],
                'notes' => '[SYNTHETIC DEMO] Travel coordination record.',
                'is_demo' => true,
            ]);
        }

        if (in_array($status, [CaseStatus::FollowUp, CaseStatus::Closed, CaseStatus::TreatmentCompleted], true)) {
            PatientFollowUp::query()->create([
                'team_id' => $team->id,
                'patient_case_id' => $case->id,
                'assigned_user_id' => $users['coordinator']->id,
                'created_by_user_id' => $users['coordinator']->id,
                'completed_by_user_id' => $status === CaseStatus::Closed
                    ? $users['coordinator']->id
                    : null,
                'uuid' => (string) Str::uuid(),
                'follow_up_type' => FollowUpType::ClinicalCheckIn,
                'status' => $status === CaseStatus::Closed
                    ? FollowUpStatus::Completed
                    : FollowUpStatus::Scheduled,
                'title' => 'Post-treatment check-in',
                'description' => '[SYNTHETIC DEMO] Follow-up reminder for demonstration.',
                'due_at' => $status === CaseStatus::FollowUp
                    ? now()->subDay()
                    : now()->addDays(14),
                'completed_at' => $status === CaseStatus::Closed ? now()->subDays(2) : null,
                'outcome_notes' => $status === CaseStatus::Closed
                    ? '[SYNTHETIC DEMO] Patient reported recovery as expected.'
                    : null,
                'is_demo' => true,
            ]);
        }
    }
}
