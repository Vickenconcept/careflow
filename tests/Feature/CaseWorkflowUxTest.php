<?php

use App\Actions\Patients\CreateCaseCommunication;
use App\Actions\Patients\MoveDocumentStorage;
use App\Actions\Patients\QueuePatientDocumentForProcessing;
use App\Actions\Patients\RecordDocumentAccess;
use App\Domains\Communications\Providers\LogOutboundCommunicationSender;
use App\Domains\Integrations\His\Contracts\HisSchedulingAdapter;
use App\Enums\AppointmentStatus;
use App\Enums\CaseStatus;
use App\Enums\CaseTaskStatus;
use App\Enums\CommunicationChannel;
use App\Enums\CommunicationDirection;
use App\Enums\CommunicationStatus;
use App\Enums\ConsentType;
use App\Enums\DoctorReviewOutcome;
use App\Enums\DoctorReviewStatus;
use App\Enums\DocumentProcessingStatus;
use App\Enums\TeamRole;
use App\Enums\TreatmentProposalStatus;
use App\Events\Patients\PatientEnquirySubmitted;
use App\Jobs\Patients\ProcessPatientDocument;
use App\Listeners\Patients\SendPatientEnquiryNotifications;
use App\Models\CaseAppointment;
use App\Models\CaseCommunication;
use App\Models\CaseTask;
use App\Models\DoctorReview;
use App\Models\DocumentAccessLog;
use App\Models\DocumentExtraction;
use App\Models\DocumentProcessingRun;
use App\Models\DocumentReviewEvent;
use App\Models\DocumentSummary;
use App\Models\DocumentTranslation;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\PatientConsent;
use App\Models\PatientDocument;
use App\Models\PatientEnquiry;
use App\Models\SavedCaseView;
use App\Models\Team;
use App\Models\TravelArrangement;
use App\Models\TreatmentProposal;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Notifications\Patients\PatientPortalUpdateStaffNotification;
use App\Support\CaseReferenceGenerator;
use App\Support\Impersonation;
use App\Support\PatientDocumentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PragmaRX\Google2FA\Google2FA;

function teamWithMember(TeamRole $role): array
{
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $team->members()->attach($user, ['role' => $role->value]);
    $user->switchTeam($team);

    return [$team, $user];
}

function caseFor(Team $team, array $attributes = []): PatientCase
{
    $patient = Patient::factory()->create(['team_id' => $team->id]);
    $enquiry = PatientEnquiry::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
    ]);

    return PatientCase::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_enquiry_id' => $enquiry->id,
        ...$attributes,
    ]);
}

function documentWithDraft(Team $team, PatientCase $case, string $reviewStatus = 'unreviewed', int $minutesAgo = 0): PatientDocument
{
    $document = PatientDocument::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $case->patient_id,
        'patient_case_id' => $case->id,
        'uploaded_at' => now()->subMinutes($minutesAgo),
    ]);

    $run = DocumentProcessingRun::factory()->create([
        'team_id' => $team->id,
        'patient_document_id' => $document->id,
    ]);

    DocumentExtraction::factory()->create([
        'team_id' => $team->id,
        'patient_document_id' => $document->id,
        'document_processing_run_id' => $run->id,
        'review_status' => $reviewStatus,
    ]);

    DocumentSummary::factory()->create([
        'team_id' => $team->id,
        'patient_document_id' => $document->id,
        'document_processing_run_id' => $run->id,
        'review_status' => $reviewStatus,
    ]);

    return $document;
}

test('doctors and nurses land on their review queue instead of the dashboard', function (TeamRole $role) {
    [$team, $user] = teamWithMember($role);

    $this->actingAs($user)
        ->get(route('dashboard', ['current_team' => $team]))
        ->assertRedirect(route('organizations.doctor-queue', ['current_team' => $team->slug]));
})->with([TeamRole::Doctor, TeamRole::Nurse]);

test('coordinators keep the dashboard as their landing page', function () {
    [$team, $user] = teamWithMember(TeamRole::Coordinator);

    $this->actingAs($user)
        ->get(route('dashboard', ['current_team' => $team]))
        ->assertOk();
});

test('the cases list exposes quick view counts for the staff member', function () {
    [$team, $coordinator] = teamWithMember(TeamRole::Coordinator);

    caseFor($team, ['status' => CaseStatus::AwaitingDocuments, 'assigned_coordinator_id' => $coordinator->id]);
    caseFor($team, ['status' => CaseStatus::AwaitingDocuments, 'assigned_coordinator_id' => null]);
    caseFor($team, ['status' => CaseStatus::AwaitingDoctorReview, 'assigned_coordinator_id' => null]);

    $this->actingAs($coordinator)
        ->get(route('organizations.cases.index', ['current_team' => $team]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('quickViewCounts.all', 3)
            ->where('quickViewCounts.mine', 1)
            ->where('quickViewCounts.unassigned', 2)
            ->where('quickViewCounts.awaiting_documents', 2)
            ->where('quickViewCounts.awaiting_doctor_review', 1)
            ->where('quickViewCounts.awaiting_patient_response', 0));
});

test('quick view counts never include cases outside the doctor visibility', function () {
    [$team, $doctor] = teamWithMember(TeamRole::Doctor);

    caseFor($team, ['assigned_doctor_id' => $doctor->id]);
    caseFor($team, ['assigned_doctor_id' => null]);

    $this->actingAs($doctor)
        ->get(route('organizations.cases.index', ['current_team' => $team]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('quickViewCounts.all', 1)
            ->where('quickViewCounts.mine', 1));
});

test('the pending work count only includes my open tasks in the current team', function () {
    [$team, $coordinator] = teamWithMember(TeamRole::Coordinator);
    $case = caseFor($team);
    $someoneElse = User::factory()->create();

    CaseTask::factory()->create(['team_id' => $team->id, 'patient_case_id' => $case->id, 'assigned_user_id' => $coordinator->id]);
    CaseTask::factory()->create(['team_id' => $team->id, 'patient_case_id' => $case->id, 'assigned_user_id' => $coordinator->id, 'status' => CaseTaskStatus::Completed]);
    CaseTask::factory()->create(['team_id' => $team->id, 'patient_case_id' => $case->id, 'assigned_user_id' => $someoneElse->id]);

    $this->actingAs($coordinator)
        ->get(route('organizations.cases.index', ['current_team' => $team]))
        ->assertInertia(fn (Assert $page) => $page->where('pendingWorkCount', 1));
});

test('marking a document reviewed records an audit event and moves to the next unreviewed document', function () {
    [$team, $coordinator] = teamWithMember(TeamRole::Coordinator);
    $case = caseFor($team, ['status' => CaseStatus::AwaitingCoordinatorReview]);
    $first = documentWithDraft($team, $case, minutesAgo: 10);
    $second = documentWithDraft($team, $case, minutesAgo: 5);

    $this->actingAs($coordinator)
        ->post(route('organizations.documents.review.confirm', [
            'current_team' => $team,
            'case' => $case,
            'document' => $first,
        ]))
        ->assertRedirect(route('organizations.documents.show', [
            'current_team' => $team->slug,
            'case' => $case->reference,
            'document' => $second->uuid,
        ]));

    expect($first->currentExtraction()->first()->review_status)->toBe('reviewed')
        ->and($first->currentSummary()->first()->review_status)->toBe('reviewed')
        ->and(DocumentReviewEvent::query()->where('patient_document_id', $first->id)->count())->toBe(2)
        ->and($second->currentExtraction()->first()->review_status)->toBe('unreviewed');
});

test('confirming the last unreviewed document returns to the case documents tab', function () {
    [$team, $coordinator] = teamWithMember(TeamRole::Coordinator);
    $case = caseFor($team);
    $only = documentWithDraft($team, $case);

    $this->actingAs($coordinator)
        ->post(route('organizations.documents.review.confirm', [
            'current_team' => $team,
            'case' => $case,
            'document' => $only,
        ]))
        ->assertRedirect(route('organizations.cases.show', [
            'current_team' => $team->slug,
            'case' => $case->reference,
        ]).'?tab=documents');
});

test('the document page reports review progress across the case', function () {
    [$team, $coordinator] = teamWithMember(TeamRole::Coordinator);
    $case = caseFor($team);
    $first = documentWithDraft($team, $case, 'reviewed', 10);
    $second = documentWithDraft($team, $case, 'unreviewed', 5);

    $this->actingAs($coordinator)
        ->get(route('organizations.documents.show', [
            'current_team' => $team,
            'case' => $case,
            'document' => $first,
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('navigation.position', 1)
            ->where('navigation.total', 2)
            ->where('navigation.pending', 1)
            ->where('navigation.prev_url', null)
            ->where('navigation.next_pending_url', route('organizations.documents.show', [
                'current_team' => $team->slug,
                'case' => $case->reference,
                'document' => $second->uuid,
            ])));
});

test('staff without AI review permission cannot mark a document reviewed', function () {
    [$team, $viewer] = teamWithMember(TeamRole::ManagementViewer);
    $case = caseFor($team);
    $document = documentWithDraft($team, $case);

    $this->actingAs($viewer)
        ->post(route('organizations.documents.review.confirm', [
            'current_team' => $team,
            'case' => $case,
            'document' => $document,
        ]))
        ->assertForbidden();

    expect($document->currentExtraction()->first()->review_status)->toBe('unreviewed');
});

test('a document without AI output cannot be marked reviewed', function () {
    [$team, $coordinator] = teamWithMember(TeamRole::Coordinator);
    $case = caseFor($team);
    $document = PatientDocument::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $case->patient_id,
        'patient_case_id' => $case->id,
    ]);

    $this->actingAs($coordinator)
        ->post(route('organizations.documents.review.confirm', [
            'current_team' => $team,
            'case' => $case,
            'document' => $document,
        ]))
        ->assertSessionHasErrors('corrections');
});

test('patient tracking exposes a localized state key and journey stage without internal status', function (CaseStatus $status, string $state, ?int $stage) {
    [$team] = teamWithMember(TeamRole::Coordinator);
    $case = caseFor($team, ['status' => $status]);
    $token = $case->issuePortalAccessToken();

    $this->get(route('portal.cases.show', [
        'organization' => $team->slug,
        'reference' => $case->reference,
        'token' => $token,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('case.state', $state)
            ->where('case.stage', $stage)
            ->missing('case.internal_status'));
})->with([
    'new enquiry' => [CaseStatus::NewEnquiry, 'received', 1],
    'awaiting coordinator review' => [CaseStatus::AwaitingCoordinatorReview, 'received', 2],
    'awaiting documents' => [CaseStatus::AwaitingDocuments, 'awaiting_documents', 2],
    'doctor review' => [CaseStatus::AwaitingDoctorReview, 'clinical_review', 3],
    'proposal sent' => [CaseStatus::ProposalSent, 'proposal', 4],
    'travel' => [CaseStatus::TravelCoordination, 'appointment', 5],
    'follow up' => [CaseStatus::FollowUp, 'follow_up', 6],
    'closed' => [CaseStatus::Closed, 'closed', null],
    'declined' => [CaseStatus::Declined, 'declined', null],
]);

test('every case status maps to a patient state the portal can explain', function () {
    $known = ['received', 'awaiting_documents', 'clinical_review', 'proposal', 'appointment', 'follow_up', 'closed', 'declined'];

    foreach (CaseStatus::cases() as $status) {
        expect($known)->toContain($status->patientStateKey());
    }
});

test('the intake confirmation uses the patient preferred language', function () {
    [$team] = teamWithMember(TeamRole::Coordinator);
    $case = caseFor($team);
    $case->patient->update(['preferred_language' => 'tr']);

    $this->get(route('portal.intake.confirmation', [
        'organization' => $team->slug,
        'reference' => $case->reference,
        'token' => 'any-token',
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('portal/intake-confirmation')
            ->where('locale', 'tr'));
});

test('coordinators can send a case from coordinator review to doctor review', function () {
    [$team, $coordinator] = teamWithMember(TeamRole::Coordinator);
    $case = caseFor($team, ['status' => CaseStatus::AwaitingCoordinatorReview]);

    $this->actingAs($coordinator)
        ->get(route('organizations.cases.show', ['current_team' => $team, 'case' => $case]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('availableStatusTransitions', fn ($transitions) => collect($transitions)->pluck('value')->contains('awaiting_doctor_review')
                && collect($transitions)->pluck('value')->contains('additional_information_required')));

    $this->actingAs($coordinator)
        ->patch(route('organizations.cases.status.update', ['current_team' => $team, 'case' => $case]), [
            'status' => 'awaiting_doctor_review',
            'note' => 'Coordinator review complete. Sent to doctor review.',
        ])
        ->assertRedirect();

    expect($case->fresh()->status)->toBe(CaseStatus::AwaitingDoctorReview);
});

test('case documents report their AI review state for the coordinator checklist', function () {
    [$team, $coordinator] = teamWithMember(TeamRole::Coordinator);
    $case = caseFor($team, ['status' => CaseStatus::AwaitingCoordinatorReview]);
    documentWithDraft($team, $case, 'unreviewed', 10);
    documentWithDraft($team, $case, 'reviewed', 5);

    $this->actingAs($coordinator)
        ->get(route('organizations.cases.show', ['current_team' => $team, 'case' => $case]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('case.documents', fn ($documents) => collect($documents)->pluck('ai_review')->sort()->values()->all() === ['reviewed', 'unreviewed']));
});

test('the patient portal shows only approved patient-portal messages', function () {
    [$team] = teamWithMember(TeamRole::Coordinator);
    $case = caseFor($team);
    $token = $case->issuePortalAccessToken();

    $make = fn (array $attributes) => CaseCommunication::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'direction' => CommunicationDirection::Outbound,
        'channel' => CommunicationChannel::PatientPortal,
        'status' => CommunicationStatus::Approved,
        'approved_at' => now(),
        ...$attributes,
    ]);

    $make(['body' => 'Visible approved message']);
    $make(['body' => 'Sent message', 'status' => CommunicationStatus::Sent, 'sent_at' => now()]);
    $make(['body' => 'Draft awaiting approval', 'status' => CommunicationStatus::AwaitingApproval, 'approved_at' => null]);
    $make(['body' => 'Logged only', 'status' => CommunicationStatus::Logged]);
    $make(['body' => 'Internal note', 'channel' => CommunicationChannel::Internal]);
    $make(['body' => 'Email to patient', 'channel' => CommunicationChannel::Email]);
    $make(['body' => 'Inbound from patient', 'direction' => CommunicationDirection::Inbound]);

    $this->get(route('portal.cases.show', [
        'organization' => $team->slug,
        'reference' => $case->reference,
        'token' => $token,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('messages', fn ($messages) => collect($messages)->pluck('body')->sort()->values()->all() === ['Sent message', 'Visible approved message']));
});

test('the patient portal shows only confirmed hospital-verified appointments', function () {
    [$team] = teamWithMember(TeamRole::Coordinator);
    $case = caseFor($team);
    $token = $case->issuePortalAccessToken();

    $make = fn (array $attributes) => CaseAppointment::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'status' => AppointmentStatus::Confirmed,
        'hospital_slot_verified' => true,
        'confirmed_starts_at' => now()->addDays(3),
        'confirmed_ends_at' => now()->addDays(3)->addHour(),
        'location' => 'Main building',
        'notes' => 'Internal coordinator note',
        ...$attributes,
    ]);

    $make(['location' => 'Visible clinic']);
    $make(['status' => AppointmentStatus::Proposed, 'location' => 'Only proposed']);
    $make(['hospital_slot_verified' => false, 'location' => 'Not verified']);

    $this->get(route('portal.cases.show', [
        'organization' => $team->slug,
        'reference' => $case->reference,
        'token' => $token,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('appointments', 1)
            ->where('appointments.0.location', 'Visible clinic')
            ->missing('appointments.0.notes'));
});

function portalUrl(Team $team, PatientCase $case, string $suffix = ''): string
{
    return "/p/{$team->slug}/cases/{$case->reference}{$suffix}";
}

test('a patient can upload additional documents through the tracking page', function () {
    Storage::fake('local');
    Queue::fake();
    Notification::fake();

    [$team, $coordinator] = teamWithMember(TeamRole::Coordinator);
    $case = caseFor($team, ['status' => CaseStatus::AwaitingDocuments, 'assigned_coordinator_id' => $coordinator->id]);
    $token = $case->issuePortalAccessToken();

    $this->post(portalUrl($team, $case, '/documents'), [
        'token' => $token,
        'documents' => [UploadedFile::fake()->create('scan.pdf', 50, 'application/pdf')],
        'document_categories' => ['laboratory_report'],
    ])->assertRedirect(route('portal.cases.show', [
        'organization' => $team->slug,
        'reference' => $case->reference,
        'token' => $token,
    ]));

    $document = PatientDocument::query()->where('patient_case_id', $case->id)->first();

    expect($document)->not->toBeNull()
        ->and($document->category->value)->toBe('laboratory_report')
        ->and($case->fresh()->status)->toBe(CaseStatus::DocumentsReceived)
        ->and(CaseTask::query()->where('patient_case_id', $case->id)->where('assigned_user_id', $coordinator->id)->exists())->toBeTrue();

    Storage::disk('local')->assertExists($document->path);
    Notification::assertSentTo($coordinator, PatientPortalUpdateStaffNotification::class);
});

test('portal uploads reject invalid tokens, other cases tokens, closed cases and wrong file types', function () {
    Storage::fake('local');
    Queue::fake();
    Notification::fake();

    [$team] = teamWithMember(TeamRole::Coordinator);
    $case = caseFor($team, ['status' => CaseStatus::AwaitingDocuments]);
    $other = caseFor($team, ['status' => CaseStatus::AwaitingDocuments]);
    $closed = caseFor($team, ['status' => CaseStatus::Closed]);
    $token = $case->issuePortalAccessToken();
    $otherToken = $other->issuePortalAccessToken();
    $closedToken = $closed->issuePortalAccessToken();
    $pdf = fn () => [UploadedFile::fake()->create('scan.pdf', 10, 'application/pdf')];

    $this->post(portalUrl($team, $case, '/documents'), ['token' => 'wrong', 'documents' => $pdf()])->assertForbidden();
    $this->post(portalUrl($team, $case, '/documents'), ['token' => $otherToken, 'documents' => $pdf()])->assertForbidden();
    $this->post(portalUrl($team, $closed, '/documents'), ['token' => $closedToken, 'documents' => $pdf()])->assertForbidden();
    $this->post(portalUrl($team, $case, '/documents'), [
        'token' => $token,
        'documents' => [UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream')],
    ])->assertSessionHasErrors('documents.0');

    expect(PatientDocument::query()->count())->toBe(0)
        ->and($case->fresh()->status)->toBe(CaseStatus::AwaitingDocuments);
});

test('a patient reply is logged for staff and appears in their own thread', function () {
    Notification::fake();

    [$team, $coordinator] = teamWithMember(TeamRole::Coordinator);
    $case = caseFor($team, ['assigned_coordinator_id' => $coordinator->id]);
    $token = $case->issuePortalAccessToken();

    $this->post(portalUrl($team, $case, '/messages'), ['token' => $token, 'body' => 'I have uploaded my blood tests.'])
        ->assertRedirect();

    $communication = CaseCommunication::query()->where('patient_case_id', $case->id)->first();

    expect($communication->direction)->toBe(CommunicationDirection::Inbound)
        ->and($communication->channel)->toBe(CommunicationChannel::PatientPortal)
        ->and($communication->status)->toBe(CommunicationStatus::Logged);

    Notification::assertSentTo($coordinator, PatientPortalUpdateStaffNotification::class);

    $this->get(route('portal.cases.show', ['organization' => $team->slug, 'reference' => $case->reference, 'token' => $token]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('messages.0.body', 'I have uploaded my blood tests.')
            ->where('messages.0.from_patient', true)
            ->where('canRespond', true));
});

test('portal replies are validated and cannot target another case', function () {
    [$team] = teamWithMember(TeamRole::Coordinator);
    $case = caseFor($team);
    $other = caseFor($team);
    $token = $case->issuePortalAccessToken();

    $this->post(portalUrl($team, $case, '/messages'), ['token' => $token, 'body' => ''])->assertSessionHasErrors('body');
    $this->post(portalUrl($team, $other, '/messages'), ['token' => $token, 'body' => 'Hello'])->assertForbidden();

    expect(CaseCommunication::query()->count())->toBe(0);
});

test('my work lists only the signed-in staff members own open items', function () {
    [$team, $coordinator] = teamWithMember(TeamRole::Coordinator);
    $other = User::factory()->create();
    $team->members()->attach($other, ['role' => TeamRole::Coordinator->value]);
    $case = caseFor($team);

    CaseTask::factory()->create(['team_id' => $team->id, 'patient_case_id' => $case->id, 'assigned_user_id' => $coordinator->id, 'title' => 'Mine open', 'due_at' => now()->subDay()]);
    CaseTask::factory()->create(['team_id' => $team->id, 'patient_case_id' => $case->id, 'assigned_user_id' => $coordinator->id, 'title' => 'Mine done', 'status' => CaseTaskStatus::Completed]);
    CaseTask::factory()->create(['team_id' => $team->id, 'patient_case_id' => $case->id, 'assigned_user_id' => $other->id, 'title' => 'Not mine']);
    CaseCommunication::factory()->create(['team_id' => $team->id, 'patient_case_id' => $case->id, 'status' => CommunicationStatus::AwaitingApproval, 'direction' => CommunicationDirection::Outbound]);
    CaseAppointment::factory()->create(['team_id' => $team->id, 'patient_case_id' => $case->id, 'status' => AppointmentStatus::Proposed]);

    $this->actingAs($coordinator)
        ->get(route('organizations.work', ['current_team' => $team]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('sections.tasks', 1)
            ->where('sections.tasks.0.title', 'Mine open')
            ->where('sections.tasks.0.overdue', true)
            ->has('sections.messages', 1)
            ->has('sections.appointments', 1)
            ->where('canApproveMessages', true));
});

test('my work never leaks items from cases the doctor cannot open', function () {
    [$team, $doctor] = teamWithMember(TeamRole::Doctor);
    $mine = caseFor($team, ['assigned_doctor_id' => $doctor->id]);
    $hidden = caseFor($team, ['assigned_doctor_id' => null]);

    CaseTask::factory()->create(['team_id' => $team->id, 'patient_case_id' => $mine->id, 'assigned_user_id' => $doctor->id, 'title' => 'Visible task']);
    CaseTask::factory()->create(['team_id' => $team->id, 'patient_case_id' => $hidden->id, 'assigned_user_id' => $doctor->id, 'title' => 'Hidden task']);
    CaseAppointment::factory()->create(['team_id' => $team->id, 'patient_case_id' => $hidden->id, 'status' => AppointmentStatus::Proposed]);

    $this->actingAs($doctor)
        ->get(route('organizations.work', ['current_team' => $team]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('sections.tasks', 1)
            ->where('sections.tasks.0.title', 'Visible task')
            ->has('sections.appointments', 0)
            ->where('canApproveMessages', false)
            ->has('sections.messages', 0));
});

test('management viewers cannot open the work page', function () {
    [$team, $viewer] = teamWithMember(TeamRole::ManagementViewer);

    $this->actingAs($viewer)
        ->get(route('organizations.work', ['current_team' => $team]))
        ->assertForbidden();
});

test('opening previewing and downloading a document is recorded in the access log', function () {
    Storage::fake('local');
    [$team, $coordinator] = teamWithMember(TeamRole::Coordinator);
    $case = caseFor($team);
    $document = documentWithDraft($team, $case);
    Storage::disk('local')->put($document->path, 'demo');
    $params = ['current_team' => $team, 'case' => $case, 'document' => $document];

    $this->actingAs($coordinator)->get(route('organizations.documents.show', $params))->assertOk();
    $this->actingAs($coordinator)->get(route('organizations.documents.preview', $params));
    $this->actingAs($coordinator)->get(route('organizations.documents.download', $params));

    expect(DocumentAccessLog::query()->where('patient_document_id', $document->id)->pluck('action')->sort()->values()->all())
        ->toBe(['download', 'preview', 'view'])
        ->and(DocumentAccessLog::query()->first()->user_id)->toBe($coordinator->id)
        ->and(DocumentAccessLog::query()->first()->case_reference)->toBe($case->reference);
});

test('automatic document page polling does not flood the access log', function () {
    [$team, $coordinator] = teamWithMember(TeamRole::Coordinator);
    $case = caseFor($team);
    $document = documentWithDraft($team, $case);

    $this->actingAs($coordinator)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'organizations/documents/show', 'X-Inertia-Partial-Data' => 'document,ai'])
        ->get(route('organizations.documents.show', ['current_team' => $team, 'case' => $case, 'document' => $document]));

    expect(DocumentAccessLog::query()->count())->toBe(0);
});

test('a forbidden document request is not logged as access', function () {
    [$team] = teamWithMember(TeamRole::Coordinator);
    [, $viewer] = teamWithMember(TeamRole::ManagementViewer);
    $viewer->switchTeam($team);
    $team->members()->attach($viewer, ['role' => TeamRole::ManagementViewer->value]);
    $case = caseFor($team);
    $document = documentWithDraft($team, $case);

    $this->actingAs($viewer)
        ->get(route('organizations.documents.show', ['current_team' => $team, 'case' => $case, 'document' => $document]))
        ->assertForbidden();

    expect(DocumentAccessLog::query()->count())->toBe(0);
});

test('only organization administrators can read the audit log', function () {
    [$team, $admin] = teamWithMember(TeamRole::Admin);
    $coordinator = User::factory()->create();
    $team->members()->attach($coordinator, ['role' => TeamRole::Coordinator->value]);
    $coordinator->switchTeam($team);
    $case = caseFor($team);

    DocumentAccessLog::factory()->create(['team_id' => $team->id, 'user_id' => $admin->id, 'case_reference' => $case->reference]);
    $foreignTeam = Team::factory()->create();
    DocumentAccessLog::factory()->create(['team_id' => $foreignTeam->id, 'case_reference' => 'OTHER-TENANT']);

    $this->actingAs($coordinator)->get(route('organizations.audit', ['current_team' => $team]))->assertForbidden();

    $this->actingAs($admin)
        ->get(route('organizations.audit', ['current_team' => $team]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('view', 'documents')
            ->has('documentAccess.data', 1)
            ->where('documentAccess.data.0.case_reference', $case->reference));

    $this->actingAs($admin)
        ->get(route('organizations.audit', ['current_team' => $team, 'view' => 'activity']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('view', 'activity')->has('activity.data'));
});

test('the doctor queue reports review turnaround from completed reviews only', function () {
    [$team, $doctor] = teamWithMember(TeamRole::Doctor);
    $case = caseFor($team, ['assigned_doctor_id' => $doctor->id, 'status' => CaseStatus::AwaitingDoctorReview]);
    $otherDoctor = User::factory()->create();
    $team->members()->attach($otherDoctor, ['role' => TeamRole::Doctor->value]);

    $review = fn (array $attributes) => DoctorReview::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'reviewer_user_id' => $doctor->id,
        'status' => DoctorReviewStatus::Completed,
        ...$attributes,
    ]);

    $review(['started_at' => now()->subHours(10), 'completed_at' => now()->subHours(8), 'outcome' => DoctorReviewOutcome::ReadyForProposal]);
    $review(['started_at' => now()->subHours(6), 'completed_at' => now()->subHours(2)]);
    $review(['status' => DoctorReviewStatus::InProgress, 'started_at' => now()->subDays(2), 'completed_at' => null]);
    $review(['reviewer_user_id' => $otherDoctor->id, 'started_at' => now()->subHours(50), 'completed_at' => now()]);

    $this->actingAs($doctor)
        ->get(route('organizations.doctor-queue', ['current_team' => $team]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.awaiting_review', 1)
            ->where('stats.reviews_last_90_days', 2)
            ->where('stats.average_review_hours', 3)
            ->has('recentlyReviewed', 2));
});

test('upcoming consultations only include confirmed verified future appointments for that doctor', function () {
    [$team, $doctor] = teamWithMember(TeamRole::Doctor);
    $case = caseFor($team, ['assigned_doctor_id' => $doctor->id]);
    $other = User::factory()->create();

    $appointment = fn (array $attributes) => CaseAppointment::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'doctor_id' => $doctor->id,
        'status' => AppointmentStatus::Confirmed,
        'hospital_slot_verified' => true,
        'confirmed_starts_at' => now()->addDay(),
        ...$attributes,
    ]);

    $appointment(['location' => 'Room 4']);
    $appointment(['status' => AppointmentStatus::Proposed]);
    $appointment(['hospital_slot_verified' => false]);
    $appointment(['confirmed_starts_at' => now()->subDay()]);
    $appointment(['doctor_id' => $other->id]);

    $this->actingAs($doctor)
        ->get(route('organizations.doctor-queue', ['current_team' => $team]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('upcoming', 1)
            ->where('upcoming.0.location', 'Room 4'));
});

test('case managers can export the filtered visible cases as CSV with formula injection neutralised', function () {
    [$team, $coordinator] = teamWithMember(TeamRole::Coordinator);
    $evil = caseFor($team, ['status' => CaseStatus::AwaitingDocuments]);
    $evil->patient->update(['first_name' => '=HYPERLINK("http://evil")', 'last_name' => 'Test']);
    caseFor($team, ['status' => CaseStatus::Closed]);
    $foreign = Team::factory()->create();
    caseFor($foreign, ['status' => CaseStatus::AwaitingDocuments]);

    $response = $this->actingAs($coordinator)
        ->get(route('organizations.cases.export', ['current_team' => $team, 'status' => 'awaiting_documents']))
        ->assertOk()
        ->assertDownload();

    $csv = $response->streamedContent();

    expect($csv)->toContain('Case reference')
        ->toContain($evil->reference)
        ->toContain("'=HYPERLINK")
        ->not->toContain('Closed')
        ->and(substr_count(trim($csv), "\n"))->toBe(1);
});

test('only roles that manage cases can export, and doctors only ever export their own cases', function () {
    [$team, $doctor] = teamWithMember(TeamRole::Doctor);
    $viewer = User::factory()->create();
    $team->members()->attach($viewer, ['role' => TeamRole::ManagementViewer->value]);
    $viewer->switchTeam($team);

    $this->actingAs($doctor)->get(route('organizations.cases.export', ['current_team' => $team]))->assertForbidden();
    $this->actingAs($viewer)->get(route('organizations.cases.export', ['current_team' => $team]))->assertForbidden();
});

test('the cases page tells the interface whether the user may export', function () {
    [$team, $coordinator] = teamWithMember(TeamRole::Coordinator);
    [$doctorTeam, $doctor] = [$team, User::factory()->create()];
    $team->members()->attach($doctor, ['role' => TeamRole::Doctor->value]);
    $doctor->switchTeam($doctorTeam);

    $this->actingAs($coordinator)->get(route('organizations.cases.index', ['current_team' => $team]))
        ->assertInertia(fn (Assert $page) => $page->where('permissions.canExport', true));
    $this->actingAs($doctor)->get(route('organizations.cases.index', ['current_team' => $team]))
        ->assertInertia(fn (Assert $page) => $page->where('permissions.canExport', false));
});

function adminWithTeam(): array
{
    [$team, $admin] = teamWithMember(TeamRole::Admin);
    $team->ensureOrganizationSetting();

    return [$team, $admin];
}

function disableModule(Team $team, string ...$modules): void
{
    $team->ensureOrganizationSetting()->update(['disabled_modules' => $modules]);
    $team->unsetRelation('organizationSetting');
}

test('administrators can save module and notification preferences', function () {
    [$team, $admin] = adminWithTeam();

    $modules = ['proposals' => true, 'appointments' => false, 'travel' => true, 'communications' => true];
    $notifications = ['staff_new_enquiry' => true, 'staff_patient_update' => false, 'staff_case_assigned' => true, 'patient_confirmation' => false];

    $this->actingAs($admin)
        ->patch(route('organizations.preferences.update', ['current_team' => $team]), compact('modules', 'notifications'))
        ->assertRedirect();

    $settings = $team->organizationSetting()->first();

    expect($settings->disabled_modules)->toBe(['appointments'])
        ->and($settings->moduleEnabled('appointments'))->toBeFalse()
        ->and($settings->moduleEnabled('proposals'))->toBeTrue()
        ->and($settings->notificationEnabled('staff_patient_update'))->toBeFalse()
        ->and($settings->notificationEnabled('patient_confirmation'))->toBeFalse()
        ->and($settings->notificationEnabled('staff_new_enquiry'))->toBeTrue();
});

test('preference pages and the roles matrix are limited to administrators', function () {
    [$team, $admin] = adminWithTeam();
    $coordinator = User::factory()->create();
    $team->members()->attach($coordinator, ['role' => TeamRole::Coordinator->value]);
    $coordinator->switchTeam($team);

    foreach (['organizations.preferences.edit', 'organizations.roles'] as $name) {
        $this->actingAs($coordinator)->get(route($name, ['current_team' => $team]))->assertForbidden();
        $this->actingAs($admin)->get(route($name, ['current_team' => $team]))->assertOk();
    }

    $this->actingAs($coordinator)
        ->patch(route('organizations.preferences.update', ['current_team' => $team]), ['modules' => [], 'notifications' => []])
        ->assertForbidden();
});

test('preferences reject incomplete submissions', function () {
    [$team, $admin] = adminWithTeam();

    $this->actingAs($admin)
        ->patch(route('organizations.preferences.update', ['current_team' => $team]), ['modules' => ['proposals' => true], 'notifications' => []])
        ->assertSessionHasErrors(['modules.appointments', 'notifications.staff_new_enquiry']);
});

test('the roles matrix never grants clinical approval to coordinators', function () {
    [$team, $admin] = adminWithTeam();

    $this->actingAs($admin)
        ->get(route('organizations.roles', ['current_team' => $team]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('groups', function ($groups) {
                $permission = collect($groups)->flatMap(fn ($group) => $group['permissions'])->firstWhere('value', 'clinical:approve');

                return $permission !== null
                    && collect($permission['roles'])->contains('doctor')
                    && ! collect($permission['roles'])->contains('coordinator');
            }));
});

test('a disabled module is blocked for staff, hidden from the case page and hidden from patients', function () {
    [$team, $coordinator] = teamWithMember(TeamRole::Coordinator);
    $team->ensureOrganizationSetting();
    $case = caseFor($team, ['status' => CaseStatus::AwaitingDocuments]);
    $token = $case->issuePortalAccessToken();
    CaseCommunication::factory()->create([
        'team_id' => $team->id, 'patient_case_id' => $case->id, 'direction' => CommunicationDirection::Outbound,
        'channel' => CommunicationChannel::PatientPortal, 'status' => CommunicationStatus::Approved, 'approved_at' => now(),
    ]);

    disableModule($team, 'proposals', 'communications');

    $this->actingAs($coordinator)
        ->post(route('organizations.proposals.store', ['current_team' => $team, 'case' => $case]), [])
        ->assertForbidden();
    $this->actingAs($coordinator)
        ->post(route('organizations.communications.store', ['current_team' => $team, 'case' => $case]), ['channel' => 'internal', 'body' => 'x'])
        ->assertForbidden();

    $this->actingAs($coordinator)
        ->get(route('organizations.cases.show', ['current_team' => $team, 'case' => $case]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('case.proposals', null)
            ->where('case.communications', null)
            ->where('case.appointments', fn ($value) => $value !== null));

    $this->get(route('portal.cases.show', ['organization' => $team->slug, 'reference' => $case->reference, 'token' => $token]))
        ->assertInertia(fn (Assert $page) => $page->has('messages', 0)->where('canReply', false)->where('canRespond', true));

    $this->post(portalUrl($team, $case, '/messages'), ['token' => $token, 'body' => 'Hello'])->assertForbidden();
});

test('a disabled appointments module hides appointments from patients, my work and doctors', function () {
    [$team, $doctor] = teamWithMember(TeamRole::Doctor);
    $team->ensureOrganizationSetting();
    $case = caseFor($team, ['assigned_doctor_id' => $doctor->id]);
    CaseAppointment::factory()->create([
        'team_id' => $team->id, 'patient_case_id' => $case->id, 'doctor_id' => $doctor->id, 'status' => AppointmentStatus::Confirmed,
        'hospital_slot_verified' => true, 'confirmed_starts_at' => now()->addDay(),
    ]);
    $token = $case->issuePortalAccessToken();

    $this->get(route('portal.cases.show', ['organization' => $team->slug, 'reference' => $case->reference, 'token' => $token]))
        ->assertInertia(fn (Assert $page) => $page->has('appointments', 1));

    disableModule($team, 'appointments');

    $this->get(route('portal.cases.show', ['organization' => $team->slug, 'reference' => $case->reference, 'token' => $token]))
        ->assertInertia(fn (Assert $page) => $page->has('appointments', 0));
    $this->actingAs($doctor)->get(route('organizations.doctor-queue', ['current_team' => $team]))
        ->assertInertia(fn (Assert $page) => $page->has('upcoming', 0));
    $this->actingAs($doctor)->get(route('organizations.work', ['current_team' => $team]))
        ->assertInertia(fn (Assert $page) => $page->has('sections.appointments', 0));
});

test('switching a notification off stops that email but keeps the coordinator task', function () {
    Storage::fake('local');
    Queue::fake();
    Notification::fake();

    [$team, $coordinator] = teamWithMember(TeamRole::Coordinator);
    $team->ensureOrganizationSetting()->update(['notification_settings' => ['staff_patient_update' => false]]);
    $case = caseFor($team, ['assigned_coordinator_id' => $coordinator->id]);
    $token = $case->issuePortalAccessToken();

    $this->post(portalUrl($team, $case, '/messages'), ['token' => $token, 'body' => 'Hello team'])->assertRedirect();

    Notification::assertNothingSent();
    expect(CaseTask::query()->where('patient_case_id', $case->id)->count())->toBe(1);
});

test('the patient confirmation email respects the organization notification setting', function () {
    Notification::fake();

    [$team] = teamWithMember(TeamRole::Coordinator);
    $team->ensureOrganizationSetting()->update(['notification_settings' => ['patient_confirmation' => false, 'staff_new_enquiry' => false]]);
    $case = caseFor($team);

    (new SendPatientEnquiryNotifications)
        ->handle(new PatientEnquirySubmitted($case->patient_enquiry_id, $case->id, 'token'));

    Notification::assertNothingSent();
});

test('administrators can switch a workflow off but not another organizations workflow', function () {
    [$team, $admin] = adminWithTeam();
    $workflow = WorkflowDefinition::query()->where('team_id', $team->id)->firstOrFail();
    $workflow->update(['is_enabled' => true]);
    $foreignTeam = Team::factory()->create();
    $foreignTeam->ensureOrganizationSetting();
    $foreign = WorkflowDefinition::query()->where('team_id', $foreignTeam->id)->firstOrFail();
    $foreign->update(['is_enabled' => true]);

    $this->actingAs($admin)
        ->patch(route('organizations.preferences.workflows.toggle', ['current_team' => $team, 'workflow' => $workflow]), ['is_enabled' => false])
        ->assertRedirect();
    $this->actingAs($admin)
        ->patch(route('organizations.preferences.workflows.toggle', ['current_team' => $team, 'workflow' => $foreign]), ['is_enabled' => false])
        ->assertNotFound();

    expect($workflow->fresh()->is_enabled)->toBeFalse()
        ->and($foreign->fresh()->is_enabled)->toBeTrue();
});

test('the patient directory lists patients, supports search and stays inside the organization', function () {
    [$team, $coordinator] = teamWithMember(TeamRole::Coordinator);
    $anna = caseFor($team);
    $anna->patient->update(['first_name' => 'Anna', 'last_name' => 'Zeta', 'email' => 'anna@example.test']);
    $ben = caseFor($team);
    $ben->patient->update(['first_name' => 'Ben', 'last_name' => 'Alpha', 'email' => 'ben@example.test']);
    caseFor($team, ['patient_id' => $anna->patient_id, 'patient_enquiry_id' => $anna->patient_enquiry_id]);
    $foreignTeam = Team::factory()->create();
    caseFor($foreignTeam)->patient->update(['first_name' => 'Foreign']);

    $this->actingAs($coordinator)
        ->get(route('organizations.patients.index', ['current_team' => $team]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('patients.data', 2)
            ->where('patients.data.0.name', 'Ben Alpha')
            ->where('patients.data.1.case_count', 2));

    $this->actingAs($coordinator)
        ->get(route('organizations.patients.index', ['current_team' => $team, 'q' => 'anna@']))
        ->assertInertia(fn (Assert $page) => $page->has('patients.data', 1)->where('patients.data.0.name', 'Anna Zeta')->where('search', 'anna@'));
});

test('doctors only see patients who have a case assigned to them and viewers cannot open the directory', function () {
    [$team, $doctor] = teamWithMember(TeamRole::Doctor);
    $mine = caseFor($team, ['assigned_doctor_id' => $doctor->id]);
    $mine->patient->update(['first_name' => 'Visible']);
    caseFor($team, ['assigned_doctor_id' => null])->patient->update(['first_name' => 'Hidden']);
    $viewer = User::factory()->create();
    $team->members()->attach($viewer, ['role' => TeamRole::ManagementViewer->value]);
    $viewer->switchTeam($team);

    $this->actingAs($doctor)
        ->get(route('organizations.patients.index', ['current_team' => $team]))
        ->assertInertia(fn (Assert $page) => $page->has('patients.data', 1)->where('patients.data.0.name', fn ($name) => str_starts_with($name, 'Visible')));

    $this->actingAs($viewer)->get(route('organizations.patients.index', ['current_team' => $team]))->assertForbidden();
});

test('coordination lists proposals, appointments and travel for visible cases with status filters', function () {
    [$team, $coordinator] = teamWithMember(TeamRole::Coordinator);
    $case = caseFor($team);

    TreatmentProposal::factory()->create(['team_id' => $team->id, 'patient_case_id' => $case->id, 'status' => TreatmentProposalStatus::Draft]);
    TreatmentProposal::factory()->create(['team_id' => $team->id, 'patient_case_id' => $case->id, 'status' => TreatmentProposalStatus::SentToPatient, 'version' => 2]);
    CaseAppointment::factory()->create(['team_id' => $team->id, 'patient_case_id' => $case->id, 'status' => AppointmentStatus::Proposed]);
    TravelArrangement::factory()->create(['team_id' => $team->id, 'patient_case_id' => $case->id]);

    $this->actingAs($coordinator)
        ->get(route('organizations.coordination', ['current_team' => $team]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('active', 'proposals')
            ->has('tabs', 3)
            ->has('rows.data', 2));

    $this->actingAs($coordinator)
        ->get(route('organizations.coordination', ['current_team' => $team, 'tab' => 'proposals', 'status' => 'draft']))
        ->assertInertia(fn (Assert $page) => $page->has('rows.data', 1)->where('rows.data.0.status_label', 'Draft'));

    $this->actingAs($coordinator)
        ->get(route('organizations.coordination', ['current_team' => $team, 'tab' => 'appointments']))
        ->assertInertia(fn (Assert $page) => $page->where('active', 'appointments')->has('rows.data', 1));

    $this->actingAs($coordinator)
        ->get(route('organizations.coordination', ['current_team' => $team, 'tab' => 'travel']))
        ->assertInertia(fn (Assert $page) => $page->where('active', 'travel')->has('rows.data', 1));

    $this->actingAs($coordinator)
        ->get(route('organizations.coordination', ['current_team' => $team, 'tab' => 'nonsense']))
        ->assertInertia(fn (Assert $page) => $page->where('active', 'proposals'));
});

test('coordination hides switched-off modules, other tenants and is closed to clinical roles', function () {
    [$team, $coordinator] = teamWithMember(TeamRole::Coordinator);
    $team->ensureOrganizationSetting();
    $case = caseFor($team);
    TreatmentProposal::factory()->create(['team_id' => $team->id, 'patient_case_id' => $case->id]);
    $foreign = caseFor(Team::factory()->create());
    TreatmentProposal::factory()->create(['team_id' => $foreign->team_id, 'patient_case_id' => $foreign->id]);

    $this->actingAs($coordinator)
        ->get(route('organizations.coordination', ['current_team' => $team]))
        ->assertInertia(fn (Assert $page) => $page->has('rows.data', 1));

    disableModule($team, 'proposals');

    $this->actingAs($coordinator)
        ->get(route('organizations.coordination', ['current_team' => $team, 'tab' => 'proposals']))
        ->assertInertia(fn (Assert $page) => $page->has('tabs', 2)->where('active', 'appointments'));

    [$doctorTeam, $doctor] = teamWithMember(TeamRole::Doctor);
    $this->actingAs($doctor)->get(route('organizations.coordination', ['current_team' => $doctorTeam]))->assertForbidden();
});

test('coordinators can save, apply and remove their own case views', function () {
    [$team, $coordinator] = teamWithMember(TeamRole::Coordinator);
    $other = User::factory()->create();
    $team->members()->attach($other, ['role' => TeamRole::Coordinator->value]);

    $this->actingAs($coordinator)
        ->post(route('organizations.case-views.store', ['current_team' => $team]), [
            'name' => 'Urgent only',
            'filters' => ['priority' => 'urgent', 'search' => ''],
        ])->assertRedirect();

    $view = SavedCaseView::query()->firstOrFail();
    expect($view->filters)->toBe(['priority' => 'urgent'])
        ->and($view->user_id)->toBe($coordinator->id);

    $this->actingAs($coordinator)
        ->get(route('organizations.cases.index', ['current_team' => $team]))
        ->assertInertia(fn (Assert $page) => $page->has('savedViews', 1)->where('savedViews.0.name', 'Urgent only'));

    $this->actingAs($other)
        ->get(route('organizations.cases.index', ['current_team' => $team]))
        ->assertInertia(fn (Assert $page) => $page->has('savedViews', 0));

    $this->actingAs($other)
        ->delete(route('organizations.case-views.destroy', ['current_team' => $team, 'view' => $view]))
        ->assertNotFound();

    $this->actingAs($coordinator)
        ->delete(route('organizations.case-views.destroy', ['current_team' => $team, 'view' => $view]))
        ->assertRedirect();

    expect(SavedCaseView::query()->count())->toBe(0);
});

test('saved views need a unique name and at least one filter', function () {
    [$team, $coordinator] = teamWithMember(TeamRole::Coordinator);
    SavedCaseView::factory()->create(['team_id' => $team->id, 'user_id' => $coordinator->id, 'name' => 'Mine']);

    $this->actingAs($coordinator)
        ->post(route('organizations.case-views.store', ['current_team' => $team]), ['name' => 'Mine', 'filters' => ['priority' => 'high']])
        ->assertSessionHasErrors('name');

    $this->actingAs($coordinator)
        ->post(route('organizations.case-views.store', ['current_team' => $team]), ['name' => 'Empty', 'filters' => []])
        ->assertStatus(422);
});

test('bulk assignment updates every visible selected case and ignores other tenants', function () {
    Queue::fake();
    [$team, $coordinator] = teamWithMember(TeamRole::Coordinator);
    $first = caseFor($team);
    $second = caseFor($team);
    $foreign = caseFor(Team::factory()->create());

    $this->actingAs($coordinator)
        ->post(route('organizations.cases.bulk-assignment', ['current_team' => $team]), [
            'case_ids' => [$first->id, $second->id, $foreign->id],
            'coordinator_id' => $coordinator->id,
        ])->assertRedirect();

    expect($first->fresh()->assigned_coordinator_id)->toBe($coordinator->id)
        ->and($second->fresh()->assigned_coordinator_id)->toBe($coordinator->id)
        ->and($foreign->fresh()->assigned_coordinator_id)->not->toBe($coordinator->id);
});

test('bulk assignment needs a target, a valid role and the assign permission', function () {
    [$team, $coordinator] = teamWithMember(TeamRole::Coordinator);
    $case = caseFor($team);
    $nurse = User::factory()->create();
    $team->members()->attach($nurse, ['role' => TeamRole::Nurse->value]);

    $this->actingAs($coordinator)
        ->post(route('organizations.cases.bulk-assignment', ['current_team' => $team]), ['case_ids' => [$case->id]])
        ->assertSessionHasErrors('assignment');

    $this->actingAs($coordinator)
        ->post(route('organizations.cases.bulk-assignment', ['current_team' => $team]), ['case_ids' => [$case->id], 'doctor_id' => $nurse->id])
        ->assertSessionHasErrors('assignment');

    [$doctorTeam, $doctor] = teamWithMember(TeamRole::Doctor);
    $this->actingAs($doctor)
        ->post(route('organizations.cases.bulk-assignment', ['current_team' => $doctorTeam]), ['case_ids' => [1], 'doctor_id' => $doctor->id])
        ->assertForbidden();
});

test('administrators can set a retention period and purge expired documents on idle closed cases', function () {
    Storage::fake('local');
    [$team, $admin] = adminWithTeam();
    $old = caseFor($team, ['status' => CaseStatus::Closed, 'last_activity_at' => now()->subMonths(14)]);
    $recent = caseFor($team, ['status' => CaseStatus::Closed, 'last_activity_at' => now()->subMonths(2)]);
    $openOld = caseFor($team, ['status' => CaseStatus::AwaitingDocuments, 'last_activity_at' => now()->subMonths(30)]);
    $expired = documentWithDraft($team, $old);
    $keptRecent = documentWithDraft($team, $recent);
    $keptOpen = documentWithDraft($team, $openOld);

    $this->actingAs($admin)
        ->patch(route('organizations.data-tools.retention', ['current_team' => $team]), ['document_retention_months' => 12])
        ->assertRedirect();

    $this->actingAs($admin)
        ->get(route('organizations.data-tools', ['current_team' => $team]))
        ->assertInertia(fn (Assert $page) => $page->where('retentionMonths', 12)->where('expiredDocumentCount', 1));

    $this->actingAs($admin)
        ->post(route('organizations.data-tools.purge', ['current_team' => $team]))
        ->assertRedirect();

    expect(PatientDocument::query()->whereKey($expired->id)->exists())->toBeFalse()
        ->and(PatientDocument::query()->whereKey($keptRecent->id)->exists())->toBeTrue()
        ->and(PatientDocument::query()->whereKey($keptOpen->id)->exists())->toBeTrue()
        ->and($old->activities()->where('summary', 'like', 'Document removed under the retention policy%')->exists())->toBeTrue();
});

test('purging without a retention period is refused', function () {
    [$team, $admin] = adminWithTeam();

    $this->actingAs($admin)
        ->post(route('organizations.data-tools.purge', ['current_team' => $team]))
        ->assertSessionHasErrors('retention');
});

test('administrators can export a patient as JSON and the export is logged on the case', function () {
    [$team, $admin] = adminWithTeam();
    $case = caseFor($team);
    $case->patient->update(['first_name' => 'Export', 'last_name' => 'Me']);

    $response = $this->actingAs($admin)
        ->get(route('organizations.data-tools.export', ['current_team' => $team, 'patient' => $case->patient->uuid]))
        ->assertOk()
        ->assertDownload('patient-'.$case->patient->uuid.'-export.json');

    $payload = json_decode($response->streamedContent(), true);

    expect($payload['patient']['first_name'])->toBe('Export')
        ->and($payload['cases'])->toHaveCount(1)
        ->and($case->activities()->where('summary', 'Patient data exported')->exists())->toBeTrue();
});

test('erasing a patient requires typing their email and removes personal data but keeps the case', function () {
    Storage::fake('local');
    [$team, $admin] = adminWithTeam();
    $case = caseFor($team);
    $case->patient->update(['first_name' => 'Real', 'last_name' => 'Person', 'email' => 'real@example.test', 'phone' => '+1 555 0100']);
    documentWithDraft($team, $case);
    $token = $case->issuePortalAccessToken();

    $url = route('organizations.data-tools.erase', ['current_team' => $team, 'patient' => $case->patient->uuid]);

    $this->actingAs($admin)->post($url, ['confirm_email' => 'wrong@example.test'])->assertSessionHasErrors('confirm_email');
    expect($case->patient->fresh()->first_name)->toBe('Real');

    $this->actingAs($admin)->post($url, ['confirm_email' => 'REAL@example.test'])->assertRedirect();

    $patient = $case->patient->fresh();
    expect($patient->first_name)->toBe('Erased')
        ->and($patient->phone)->toBeNull()
        ->and($patient->email)->toContain('erased-')
        ->and(PatientDocument::query()->where('patient_case_id', $case->id)->count())->toBe(0)
        ->and(PatientCase::query()->whereKey($case->id)->exists())->toBeTrue()
        ->and($case->fresh()->portalAccessTokenIsValid($token))->toBeFalse()
        ->and($case->activities()->where('summary', 'Patient personal data erased')->exists())->toBeTrue();
});

test('data tools are restricted to administrators of the same organization', function () {
    [$team, $coordinator] = teamWithMember(TeamRole::Coordinator);
    $case = caseFor($team);

    $this->actingAs($coordinator)->get(route('organizations.data-tools', ['current_team' => $team]))->assertForbidden();
    $this->actingAs($coordinator)
        ->post(route('organizations.data-tools.erase', ['current_team' => $team, 'patient' => $case->patient->uuid]), ['confirm_email' => $case->patient->email])
        ->assertForbidden();

    [$otherTeam, $otherAdmin] = adminWithTeam();
    $this->actingAs($otherAdmin)
        ->get(route('organizations.data-tools.export', ['current_team' => $otherTeam, 'patient' => $case->patient->uuid]))
        ->assertNotFound();
});

test('administrators can upload and remove a logo that patients see', function () {
    Storage::fake('public');
    [$team, $admin] = adminWithTeam();

    $this->actingAs($admin)
        ->post(route('organizations.logo.store', ['current_team' => $team]), ['logo' => UploadedFile::fake()->image('logo.png', 120, 60)])
        ->assertRedirect();

    $path = $team->organizationSetting()->first()->logo_path;
    Storage::disk('public')->assertExists($path);

    $this->get(route('portal.logo', ['organization' => $team->slug]))->assertOk();
    $this->get(route('portal.intake.create', ['organization' => $team->slug]))
        ->assertInertia(fn (Assert $page) => $page->where('organization.logo_url', fn ($url) => str_contains($url, '/logo')));

    $this->actingAs($admin)
        ->post(route('organizations.logo.store', ['current_team' => $team]), ['logo' => UploadedFile::fake()->create('logo.svg', 4, 'image/svg+xml')])
        ->assertSessionHasErrors('logo');

    $this->actingAs($admin)->delete(route('organizations.logo.destroy', ['current_team' => $team]))->assertRedirect();
    Storage::disk('public')->assertMissing($path);
    $this->get(route('portal.logo', ['organization' => $team->slug]))->assertNotFound();
});

test('staff can sign off the current translation once and it is recorded', function () {
    [$team, $doctor] = teamWithMember(TeamRole::Doctor);
    $case = caseFor($team, ['assigned_doctor_id' => $doctor->id]);
    $document = documentWithDraft($team, $case);
    $translation = DocumentTranslation::factory()->create([
        'team_id' => $team->id,
        'patient_document_id' => $document->id,
        'document_processing_run_id' => DocumentProcessingRun::query()->where('patient_document_id', $document->id)->value('id'),
        'is_current' => true,
    ]);

    $url = route('organizations.documents.translation.sign-off', ['current_team' => $team, 'case' => $case->reference, 'document' => $document->uuid]);

    $this->actingAs($doctor)->post($url)->assertRedirect();

    expect($translation->fresh()->reviewed_by)->toBe($doctor->id)
        ->and(DocumentReviewEvent::query()->where('field_path', 'translation_signed_off')->count())->toBe(1);

    $this->actingAs($doctor)->post($url)->assertSessionHasErrors('corrections');
});

test('platform admins manage plans, locked modules and seat limits that are enforced', function () {
    $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
    [$team, $admin] = adminWithTeam();

    $this->actingAs($platformAdmin)
        ->patch(route('platform.organizations.update', ['organization' => $team->slug]), [
            'plan' => 'standard',
            'subscription_status' => 'active',
            'seat_limit' => 1,
            'locked_modules' => ['travel'],
        ])->assertRedirect();

    $team->unsetRelation('organizationSetting');
    expect($team->moduleEnabled('travel'))->toBeFalse()
        ->and($team->moduleEnabled('proposals'))->toBeTrue();

    $this->actingAs($admin)
        ->get(route('organizations.preferences.edit', ['current_team' => $team]))
        ->assertInertia(fn (Assert $page) => $page->where('modules.2.key', 'travel')->where('modules.2.locked', true));

    $this->actingAs($admin)
        ->post(route('teams.invitations.store', ['team' => $team->slug]), ['email' => 'new@example.test', 'role' => 'coordinator'])
        ->assertSessionHasErrors('email');

    $this->actingAs($platformAdmin)
        ->get(route('platform.organizations.edit', ['organization' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('usage.staff', 1)->where('subscription.seat_limit', 1));

    $this->actingAs($admin)
        ->get(route('platform.organizations.edit', ['organization' => $team->slug]))
        ->assertForbidden();
});

test('suspended organizations are blocked for staff but reachable by impersonation', function () {
    [$team, $admin] = adminWithTeam();
    $team->organizationSetting()->update(['subscription_status' => 'suspended']);

    $this->actingAs($admin)
        ->get(route('organizations.cases.index', ['current_team' => $team]))
        ->assertForbidden();

    $this->actingAs($admin)
        ->withSession([Impersonation::SESSION_KEY => 999])
        ->get(route('organizations.cases.index', ['current_team' => $team]))
        ->assertOk();
});

test('the access log does not repeat identical opens within a minute', function () {
    [$team, $doctor] = teamWithMember(TeamRole::Doctor);
    $case = caseFor($team, ['assigned_doctor_id' => $doctor->id]);
    $document = documentWithDraft($team, $case);
    $record = app(RecordDocumentAccess::class);

    $record->handle($document, $case, $doctor, 'preview', '127.0.0.1');
    $record->handle($document, $case, $doctor, 'preview', '127.0.0.1');
    $record->handle($document, $case, $doctor, 'download', '127.0.0.1');
    $record->handle($document, $case, $doctor, 'download', '127.0.0.1');

    expect(DocumentAccessLog::query()->where('action', 'preview')->count())->toBe(1)
        ->and(DocumentAccessLog::query()->where('action', 'download')->count())->toBe(2);
});

test('two hospitals can each have the same case reference and only open their own', function () {
    [$teamA, $userA] = teamWithMember(TeamRole::Coordinator);
    [$teamB, $userB] = teamWithMember(TeamRole::Coordinator);
    $generator = app(CaseReferenceGenerator::class);

    $caseA = caseFor($teamA, ['reference' => $generator->next($teamA)]);
    $caseB = caseFor($teamB, ['reference' => $generator->next($teamB)]);

    expect($caseA->reference)->toBe($caseB->reference);

    $this->actingAs($userB)
        ->get(route('organizations.cases.show', ['current_team' => $teamB, 'case' => $caseB->reference]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('case.id', $caseB->id));

    $this->actingAs($userA)
        ->get(route('organizations.cases.show', ['current_team' => $teamA, 'case' => $caseA->reference]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('case.id', $caseA->id));

    $this->actingAs($userA)
        ->get(route('organizations.cases.show', ['current_team' => $teamB, 'case' => $caseB->reference]))
        ->assertForbidden();
});

test('new uploads go to the organization storage and are streamed back without exposing a storage url', function () {
    Storage::fake('local');
    Http::fake([
        'api.cloudinary.com/*/raw/upload' => Http::response(['public_id' => 'x'], 200),
        'api.cloudinary.com/*/raw/download*' => fn ($request) => Http::response(str_contains($request->url(), 'careflow-connection-test') ? 'careflow-connection-test' : '%PDF-1.4 stored on cloudinary', 200),
        'api.cloudinary.com/*/resources/raw/authenticated/*' => Http::response(['public_id' => 'x'], 200),
        'api.cloudinary.com/*/raw/destroy' => Http::response(['result' => 'ok'], 200),
    ]);

    [$team, $admin] = adminWithTeam();

    $this->actingAs($admin)
        ->put(route('organizations.storage.update', ['current_team' => $team]), [
            'driver' => 'cloudinary',
            'cloud_name' => 'kolan-demo',
            'api_key' => '123456789',
            'api_secret' => 'super-secret-value',
        ])->assertSessionHasNoErrors();

    $settings = $team->organizationSetting()->first();
    expect($settings->storage_driver)->toBe('cloudinary')
        ->and($settings->storage_config['api_secret'])->toBe('super-secret-value')
        ->and(DB::table('organization_settings')->where('id', $settings->id)->value('storage_config'))->not->toContain('super-secret-value');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/raw/upload')
        && collect($request->data())->contains(fn ($part) => ($part['name'] ?? null) === 'type' && ($part['contents'] ?? null) === 'authenticated')
        && collect($request->data())->contains(fn ($part) => ($part['name'] ?? null) === 'signature' && strlen((string) ($part['contents'] ?? '')) === 40));

    $this->actingAs($admin)
        ->get(route('organizations.storage.edit', ['current_team' => $team]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('driver', 'cloudinary')->where('config.has_secret', true)->where('config.api_key_hint', '…6789'))
        ->assertDontSee('super-secret-value');

    $case = caseFor($team);
    $stored = app(PatientDocumentStorage::class)->store($team, $case, UploadedFile::fake()->create('scan.pdf', 5, 'application/pdf'));
    expect($stored['disk'])->toBe('external:cloudinary');

    $document = PatientDocument::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $case->patient_id,
        'patient_case_id' => $case->id,
        'disk' => $stored['disk'],
        'path' => $stored['path'],
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('organizations.documents.preview', ['current_team' => $team, 'case' => $case->reference, 'document' => $document->uuid]))
        ->assertOk();

    expect($response->streamedContent())->toBe('%PDF-1.4 stored on cloudinary')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

test('storage settings reject failed connections, private endpoints and orphaning changes', function () {
    Http::fake(['api.cloudinary.com/*' => Http::response('denied', 401)]);
    [$team, $admin] = adminWithTeam();
    $url = route('organizations.storage.update', ['current_team' => $team]);

    $this->actingAs($admin)
        ->put($url, ['driver' => 'cloudinary', 'cloud_name' => 'x', 'api_key' => '1', 'api_secret' => 'bad'])
        ->assertSessionHasErrors('driver');

    expect($team->organizationSetting()->first()->storage_driver)->toBe('platform');

    $this->actingAs($admin)
        ->put($url, ['driver' => 's3', 'key' => 'k', 'secret' => 's', 'bucket' => 'b', 'endpoint' => 'https://127.0.0.1:9000'])
        ->assertSessionHasErrors('endpoint');

    $this->actingAs($admin)
        ->put($url, ['driver' => 's3', 'key' => 'k', 'secret' => 's', 'bucket' => 'b', 'endpoint' => 'http://example.com'])
        ->assertSessionHasErrors('endpoint');

    $this->actingAs($admin)
        ->put($url, ['driver' => 's3', 'key' => 'k', 'bucket' => 'b'])
        ->assertSessionHasErrors('secret');

    $team->organizationSetting()->first()->update(['storage_driver' => 'cloudinary', 'storage_config' => ['cloud_name' => 'a', 'api_key' => '1', 'api_secret' => 's']]);
    $case = caseFor($team);
    PatientDocument::factory()->create(['team_id' => $team->id, 'patient_id' => $case->patient_id, 'patient_case_id' => $case->id, 'disk' => 'external:cloudinary']);

    $this->actingAs($admin)->put($url, ['driver' => 'platform'])->assertSessionHasErrors('driver');
    expect($team->organizationSetting()->first()->storage_driver)->toBe('cloudinary');
});

test('only administrators can change document storage', function () {
    [$team, $coordinator] = teamWithMember(TeamRole::Coordinator);

    $this->actingAs($coordinator)->get(route('organizations.storage.edit', ['current_team' => $team]))->assertForbidden();
    $this->actingAs($coordinator)->put(route('organizations.storage.update', ['current_team' => $team]), ['driver' => 'platform'])->assertForbidden();
});

test('a document whose external storage is no longer configured fails safely', function () {
    [$team, $admin] = adminWithTeam();
    $case = caseFor($team);
    $document = PatientDocument::factory()->create(['team_id' => $team->id, 'patient_id' => $case->patient_id, 'patient_case_id' => $case->id, 'disk' => 'external:s3']);

    expect(fn () => $document->filesystem())->toThrow(RuntimeException::class);
});

test('unsafe stored mime types are never rendered inline', function () {
    Storage::fake('local');
    [$team, $admin] = adminWithTeam();
    $case = caseFor($team);
    Storage::disk('local')->put('x/evil.png', '<script>alert(1)</script>');
    $document = PatientDocument::factory()->create([
        'team_id' => $team->id, 'patient_id' => $case->patient_id, 'patient_case_id' => $case->id,
        'disk' => 'local', 'path' => 'x/evil.png', 'mime_type' => 'text/html', 'extension' => 'png',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('organizations.documents.preview', ['current_team' => $team, 'case' => $case->reference, 'document' => $document->uuid]))
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/octet-stream')
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment');
});

test('every response carries baseline security headers and portal pages are never cached', function () {
    [$team] = teamWithMember(TeamRole::Coordinator);

    $response = $this->get(route('portal.intake.create', ['organization' => $team->slug]))->assertOk();

    expect($response->headers->get('Referrer-Policy'))->toBe('no-referrer')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('registration can be restricted to invited staff', function () {
    config(['careflow.allow_registration' => false]);

    $this->post('/register', [
        'name' => 'Stranger',
        'email' => 'stranger@example.test',
        'password' => 'Str0ng-Passw0rd!x',
        'password_confirmation' => 'Str0ng-Passw0rd!x',
    ])->assertSessionHasErrors('email');

    expect(User::query()->where('email', 'stranger@example.test')->exists())->toBeFalse();

    [$team, $admin] = adminWithTeam();
    $team->invitations()->create([
        'email' => 'invited@example.test',
        'role' => TeamRole::Coordinator,
        'invited_by' => $admin->id,
        'expires_at' => now()->addDay(),
    ]);

    $this->post('/register', [
        'name' => 'Invited',
        'email' => 'invited@example.test',
        'password' => 'Str0ng-Passw0rd!x',
        'password_confirmation' => 'Str0ng-Passw0rd!x',
    ])->assertSessionHasNoErrors();

    expect(User::query()->where('email', 'invited@example.test')->exists())->toBeTrue();
});

test('another hospital can never reach documents or tasks through its own url prefix', function () {
    Storage::fake('local');
    [$teamA] = teamWithMember(TeamRole::Coordinator);
    [$teamB, $userB] = teamWithMember(TeamRole::Admin);
    $caseA = caseFor($teamA);
    $documentA = documentWithDraft($teamA, $caseA);
    $taskA = CaseTask::factory()->create(['team_id' => $teamA->id, 'patient_case_id' => $caseA->id]);

    $this->actingAs($userB)
        ->get(route('organizations.documents.preview', ['current_team' => $teamB, 'case' => $caseA->reference, 'document' => $documentA->uuid]))
        ->assertNotFound();

    $this->actingAs($userB)
        ->get(route('organizations.documents.preview', ['current_team' => $teamA, 'case' => $caseA->reference, 'document' => $documentA->uuid]))
        ->assertForbidden();

    $this->actingAs($userB)
        ->patch(route('organizations.tasks.update', ['current_team' => $teamB, 'case' => $caseA->reference, 'task' => $taskA->uuid]), ['title' => 'hijack'])
        ->assertNotFound();

    expect($taskA->fresh()->title)->not->toBe('hijack');
});

test('the preflight command flags a non production setup and names the blockers', function () {
    $this->artisan('careflow:preflight')
        ->expectsOutputToContain('APP_DEBUG is off')
        ->assertFailed();
});

test('outbound communication logs never contain full contact details or message text', function () {
    Log::spy();
    Mail::fake();
    [$team, $coordinator] = teamWithMember(TeamRole::Coordinator);
    $case = caseFor($team);
    $communication = CaseCommunication::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'channel' => CommunicationChannel::Email,
        'recipient' => 'private.patient@example.test',
        'body' => 'Your MRI shows something private',
    ]);

    app(LogOutboundCommunicationSender::class)->send($communication);

    Log::shouldHaveReceived('info')->withArgs(function ($message, $context = []) {
        $encoded = json_encode($context);

        return ! str_contains($encoded, 'private.patient@example.test') && ! str_contains($encoded, 'MRI');
    })->once();
});

test('whatsapp entries are recorded as conversations and never pretend to be sent', function () {
    [$team, $coordinator] = teamWithMember(TeamRole::Coordinator);
    $case = caseFor($team);

    $communication = app(CreateCaseCommunication::class)->handle($case, $coordinator, [
        'channel' => 'whatsapp',
        'body' => 'Patient confirmed flight details by WhatsApp.',
        'recipient' => '+905551234567',
    ]);

    expect($communication->status)->toBe(CommunicationStatus::Logged)
        ->and($communication->requires_approval)->toBeFalse();

    $draft = CaseCommunication::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'channel' => CommunicationChannel::WhatsApp,
        'recipient' => '+905551234567',
    ]);

    expect(fn () => app(LogOutboundCommunicationSender::class)->send($draft))
        ->toThrow(InvalidArgumentException::class, 'not connected');
});

test('appointment hospital references are stored as entered and never marked electronically verified', function () {
    $adapter = app(HisSchedulingAdapter::class);
    $result = $adapter->verifySlotReference(new CaseAppointment, 'kbh-2026/0042');

    expect($result)->toBe(['provider' => 'manual_entry', 'reference' => 'KBH-2026/0042', 'verified' => false]);
});

test('documents are never sent to AI without the patient consent or when the hospital has AI switched off', function () {
    Queue::fake();
    [$team, $admin] = adminWithTeam();
    $case = caseFor($team);
    $document = PatientDocument::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $case->patient_id,
        'patient_case_id' => $case->id,
        'processing_status' => DocumentProcessingStatus::Uploaded,
    ]);

    $queue = app(QueuePatientDocumentForProcessing::class);

    $team->organizationSetting()->first()->update(['ai_processing_enabled' => true]);
    $document = $document->fresh();

    $queue->handle($document);
    expect($document->fresh()->processing_status)->toBe(DocumentProcessingStatus::NeedsReview)
        ->and($document->fresh()->processing_error)->toContain('did not consent');
    Queue::assertNothingPushed();

    PatientConsent::factory()->create(['team_id' => $team->id, 'patient_id' => $case->patient_id, 'consent_type' => ConsentType::AiProcessing]);
    $queue->handle($document->fresh());
    Queue::assertPushed(ProcessPatientDocument::class);

    $team->organizationSetting()->first()->update(['ai_processing_enabled' => false]);
    $document->unsetRelation('team');
    $queue->handle($document->fresh());
    expect($document->fresh()->processing_status)->toBe(DocumentProcessingStatus::NeedsReview)
        ->and($document->fresh()->processing_error)->toContain('switched off');
});

test('staff can turn on two-factor authentication, then must pass the challenge to sign in', function () {
    $user = User::factory()->create(['password' => Hash::make('Correct-Horse-9!')]);
    $confirmed = ['auth.password_confirmed_at' => time()];

    $this->actingAs($user)->withSession($confirmed)
        ->postJson('/user/two-factor-authentication')
        ->assertOk();

    expect($user->fresh()->two_factor_secret)->not->toBeNull()
        ->and($user->fresh()->hasEnabledTwoFactorAuthentication())->toBeFalse();

    $this->actingAs($user)->withSession($confirmed)
        ->postJson('/user/confirmed-two-factor-authentication', ['code' => '000000'])
        ->assertStatus(422);

    $google = app(Google2FA::class);
    $code = $google->getCurrentOtp(decrypt($user->fresh()->two_factor_secret));

    $this->actingAs($user)->withSession($confirmed)
        ->postJson('/user/confirmed-two-factor-authentication', ['code' => $code])
        ->assertOk();

    expect($user->fresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();

    auth()->logout();

    $this->post('/login', ['email' => $user->email, 'password' => 'Correct-Horse-9!'])
        ->assertRedirect('/two-factor-challenge');

    $this->assertGuest();

    $this->post('/two-factor-challenge', ['code' => '123456'])->assertSessionHasErrors('code');
    $this->assertGuest();
});

test('mandatory two-factor sends users without it to set it up before reaching patient data', function () {
    config(['careflow.require_two_factor' => true]);
    [$team, $coordinator] = teamWithMember(TeamRole::Coordinator);

    $this->actingAs($coordinator)
        ->get(route('organizations.cases.index', ['current_team' => $team]))
        ->assertRedirect(route('security.edit'));

    $coordinator->forceFill([
        'two_factor_secret' => encrypt('SECRET'),
        'two_factor_recovery_codes' => encrypt(json_encode(['a'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->actingAs($coordinator)
        ->get(route('organizations.cases.index', ['current_team' => $team]))
        ->assertOk();
});

test('existing documents can be moved to the organization storage and back with verification', function () {
    Storage::fake('local');
    $store = [];
    Http::fake([
        'api.cloudinary.com/*/raw/upload' => function ($request) use (&$store) {
            $parts = collect($request->data())->keyBy('name');
            $store[$parts['public_id']['contents']] = $parts['file']['contents'];

            return Http::response(['public_id' => 'x'], 200);
        },
        'api.cloudinary.com/*/raw/download*' => function ($request) use (&$store) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return isset($store[$query['public_id']])
                ? Http::response($store[$query['public_id']], 200)
                : Http::response('missing', 404);
        },
        'api.cloudinary.com/*/raw/destroy' => function ($request) use (&$store) {
            unset($store[$request->data()['public_id'] ?? '']);

            return Http::response(['result' => 'ok'], 200);
        },
        'api.cloudinary.com/*/resources/raw/authenticated/*' => Http::response([], 200),
    ]);

    [$team, $admin] = adminWithTeam();
    $case = caseFor($team);
    $path = 'teams/'.$team->id.'/cases/'.$case->id.'/documents/move-me.pdf';
    Storage::disk('local')->put($path, '%PDF-move-me');
    $document = PatientDocument::factory()->create([
        'team_id' => $team->id, 'patient_id' => $case->patient_id, 'patient_case_id' => $case->id,
        'disk' => 'local', 'path' => $path, 'checksum_sha256' => hash('sha256', '%PDF-move-me'),
    ]);

    $this->actingAs($admin)
        ->post(route('organizations.storage.move', ['current_team' => $team]), ['direction' => 'to_external'])
        ->assertSessionHasErrors('driver');

    $this->actingAs($admin)->put(route('organizations.storage.update', ['current_team' => $team]), [
        'driver' => 'cloudinary', 'cloud_name' => 'demo', 'api_key' => '1', 'api_secret' => 's',
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)
        ->post(route('organizations.storage.move', ['current_team' => $team]), ['direction' => 'to_external'])
        ->assertRedirect();

    $document->refresh();
    expect($document->disk)->toBe('external:cloudinary')
        ->and($document->filesystem()->get($path))->toBe('%PDF-move-me');
    Storage::disk('local')->assertMissing($path);

    $this->actingAs($admin)
        ->post(route('organizations.storage.move', ['current_team' => $team]), ['direction' => 'to_platform'])
        ->assertRedirect();

    $document->refresh();
    expect($document->disk)->toBe('local');
    Storage::disk('local')->assertExists($path);

    $this->actingAs($admin)->put(route('organizations.storage.update', ['current_team' => $team]), ['driver' => 'platform'])
        ->assertSessionHasNoErrors();
    expect($team->organizationSetting()->first()->storage_driver)->toBe('platform');
});

test('a corrupted source file is never moved and the original record stays unchanged', function () {
    Storage::fake('local');
    [$team] = adminWithTeam();
    $team->organizationSetting()->first()->update(['storage_driver' => 'cloudinary', 'storage_config' => ['cloud_name' => 'd', 'api_key' => '1', 'api_secret' => 's']]);
    $case = caseFor($team);
    $path = 'teams/x/bad.pdf';
    Storage::disk('local')->put($path, 'tampered');
    $document = PatientDocument::factory()->create([
        'team_id' => $team->id, 'patient_id' => $case->patient_id, 'patient_case_id' => $case->id,
        'disk' => 'local', 'path' => $path, 'checksum_sha256' => hash('sha256', 'original'),
    ]);

    Http::fake();

    expect(fn () => app(MoveDocumentStorage::class)->handle($document, 'external:cloudinary'))
        ->toThrow(RuntimeException::class);

    expect($document->fresh()->disk)->toBe('local');
    Storage::disk('local')->assertExists($path);
});
