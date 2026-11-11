<?php

use App\Enums\CaseStatus;
use App\Enums\ConsentType;
use App\Enums\DocumentProcessingStatus;
use App\Events\Patients\PatientEnquirySubmitted;
use App\Jobs\Patients\ProcessPatientDocument;
use App\Listeners\Patients\QueueUploadedDocumentsForProcessing;
use App\Models\Department;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\PatientConsent;
use App\Models\PatientDocument;
use App\Models\PatientEnquiry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
});

test('public intake page renders for an organization', function () {
    $team = Team::factory()->create(['slug' => 'careflow-demo']);
    $team->ensureOrganizationSetting();
    Department::factory()->create([
        'team_id' => $team->id,
        'name' => 'Cardiology',
        'slug' => 'cardiology',
        'is_active' => true,
    ]);

    $this->get(route('portal.intake.create', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('portal/intake')
            ->where('organization.slug', 'careflow-demo')
            ->has('departments', 1));
});

test('patient can submit a multilingual intake with documents and consents', function () {
    Event::fake([PatientEnquirySubmitted::class]);
    Queue::fake();

    $team = Team::factory()->create(['slug' => 'intake-hospital']);
    $team->ensureOrganizationSetting();
    $department = Department::factory()->create([
        'team_id' => $team->id,
        'is_active' => true,
    ]);

    $file = UploadedFile::fake()->create('report.pdf', 200, 'application/pdf');

    $response = $this->post(route('portal.intake.store', $team), [
        'first_name' => 'Ayla',
        'last_name' => 'Demir',
        'date_of_birth' => '1990-04-12',
        'gender' => 'female',
        'country_of_residence' => 'TR',
        'email' => 'ayla.demir@example.test',
        'phone' => '+90 532 000 0000',
        'preferred_language' => 'tr',
        'department_id' => $department->id,
        'reason_for_contact' => 'Second opinion for cardiac evaluation',
        'diagnosis' => 'Suspected arrhythmia',
        'symptoms_or_history' => 'Palpitations for 3 months',
        'documents' => [$file],
        'document_categories' => ['medical_report'],
        'enquiry_consent' => '1',
        'health_data_consent' => '1',
        'ai_processing_consent' => '1',
    ]);

    $case = PatientCase::query()->firstOrFail();
    $patient = Patient::query()->firstOrFail();

    $response->assertRedirect();
    expect($response->headers->get('Location'))
        ->toContain(route('portal.intake.confirmation', [
            'organization' => $team->slug,
            'reference' => $case->reference,
        ], absolute: false))
        ->toContain('token=');

    expect($patient->preferred_language)->toBe('tr')
        ->and($patient->email)->toBe('ayla.demir@example.test')
        ->and($case->team_id)->toBe($team->id)
        ->and($case->status)->toBe(CaseStatus::DocumentsReceived)
        ->and($case->reference)->toStartWith('CF-'.now()->year.'-')
        ->and($case->portal_access_token_hash)->not->toBeNull();

    $document = PatientDocument::query()->firstOrFail();

    expect($document->processing_status)->toBe(DocumentProcessingStatus::Uploaded)
        ->and(Storage::disk('local')->exists($document->path))->toBeTrue();

    $this->assertDatabaseHas('patient_consents', [
        'patient_id' => $patient->id,
        'consent_type' => ConsentType::Enquiry->value,
        'accepted' => true,
    ]);

    $this->assertDatabaseHas('patient_consents', [
        'patient_id' => $patient->id,
        'consent_type' => ConsentType::AiProcessing->value,
        'accepted' => true,
    ]);

    Event::assertDispatched(PatientEnquirySubmitted::class);
});

test('intake associates returning patients by tenant email instead of duplicating', function () {
    Event::fake([PatientEnquirySubmitted::class]);

    $team = Team::factory()->create();
    $team->ensureOrganizationSetting();

    $existing = Patient::factory()->create([
        'team_id' => $team->id,
        'email' => 'returning@example.test',
        'first_name' => 'Old',
        'last_name' => 'Name',
    ]);

    $this->post(route('portal.intake.store', $team), [
        'first_name' => 'New',
        'last_name' => 'Name',
        'email' => 'returning@example.test',
        'preferred_language' => 'en',
        'reason_for_contact' => 'Follow-up enquiry',
        'enquiry_consent' => '1',
        'health_data_consent' => '1',
    ])->assertRedirect();

    expect(Patient::query()->forTeam($team)->count())->toBe(1)
        ->and($existing->fresh()->first_name)->toBe('New')
        ->and(PatientCase::query()->forTeam($team)->count())->toBe(1);
});

test('document validation jobs are queued after enquiry submission', function () {
    Queue::fake();

    $team = Team::factory()->create();
    $patient = Patient::factory()->create(['team_id' => $team->id]);
    $enquiry = PatientEnquiry::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
    ]);
    $case = PatientCase::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_enquiry_id' => $enquiry->id,
    ]);

    $path = 'teams/'.$team->id.'/cases/'.$case->id.'/documents/demo.pdf';
    Storage::disk('local')->put($path, 'demo-content');

    $document = PatientDocument::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_case_id' => $case->id,
        'path' => $path,
        'processing_status' => DocumentProcessingStatus::Uploaded,
    ]);
    PatientConsent::factory()->create(['team_id' => $team->id, 'patient_id' => $patient->id, 'consent_type' => ConsentType::AiProcessing]);

    app(QueueUploadedDocumentsForProcessing::class)->handle(
        new PatientEnquirySubmitted($enquiry->id, $case->id),
    );

    expect($document->fresh()->processing_status)->toBe(DocumentProcessingStatus::Pending);

    Queue::assertPushed(ProcessPatientDocument::class, fn ($job) => $job->documentId === $document->id);
});

test('patients can track cases only with a valid opaque token', function () {
    $team = Team::factory()->create();
    $patient = Patient::factory()->create(['team_id' => $team->id]);
    $enquiry = PatientEnquiry::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
    ]);
    $case = PatientCase::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_enquiry_id' => $enquiry->id,
        'status' => CaseStatus::AwaitingCoordinatorReview,
    ]);

    $token = $case->issuePortalAccessToken();

    $this->get(route('portal.cases.show', [
        'organization' => $team->slug,
        'reference' => $case->reference,
        'token' => $token,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('portal/case-tracking')
            ->where('case.reference', $case->reference)
            ->where('case.status', 'Received'));

    $this->get(route('portal.cases.show', [
        'organization' => $team->slug,
        'reference' => $case->reference,
        'token' => 'invalid-token',
    ]))->assertForbidden();
});

test('staff can list cases for their organization only', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;

    $patient = Patient::factory()->create(['team_id' => $team->id]);
    $enquiry = PatientEnquiry::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
    ]);
    PatientCase::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_enquiry_id' => $enquiry->id,
        'reference' => 'CF-2026-000111',
    ]);

    $foreignTeam = Team::factory()->create();
    $foreignPatient = Patient::factory()->create(['team_id' => $foreignTeam->id]);
    $foreignEnquiry = PatientEnquiry::factory()->create([
        'team_id' => $foreignTeam->id,
        'patient_id' => $foreignPatient->id,
    ]);
    PatientCase::factory()->create([
        'team_id' => $foreignTeam->id,
        'patient_id' => $foreignPatient->id,
        'patient_enquiry_id' => $foreignEnquiry->id,
        'reference' => 'CF-2026-000222',
    ]);

    $this->actingAs($owner)
        ->get(route('organizations.cases.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('organizations/cases/index')
            ->has('cases.data', 1)
            ->where('cases.data.0.reference', 'CF-2026-000111'));
});
