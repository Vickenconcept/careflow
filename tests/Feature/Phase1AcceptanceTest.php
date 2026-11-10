<?php

use App\Demo\CareFlowDemoDataset;
use App\Enums\CaseStatus;
use App\Enums\ClinicalApprovalOutcome;
use App\Enums\DoctorReviewOutcome;
use App\Enums\DocumentProcessingStatus;
use App\Models\PatientCase;
use App\Models\PatientDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
});

test('phase 1 acceptance path covers intake through doctor clinical review', function () {
    $dataset = app(CareFlowDemoDataset::class)->seed();
    $team = $dataset['team'];
    $coordinator = $dataset['users']['coordinator'];
    $doctor = $dataset['users']['doctor'];
    $outsider = User::factory()->create();

    $department = $team->departments()->where('code', 'CARD')->firstOrFail();

    // Public multilingual intake with private document storage + async mock AI (sync in tests).
    $file = UploadedFile::fake()->create('synthetic-report.pdf', 120, 'application/pdf');

    $this->post(route('portal.intake.store', $team), [
        'first_name' => 'Acceptance',
        'last_name' => 'Patient',
        'date_of_birth' => '1988-06-01',
        'gender' => 'female',
        'country_of_residence' => 'TR',
        'email' => 'acceptance.patient@example.test',
        'phone' => '+90 532 111 2233',
        'preferred_language' => 'tr',
        'department_id' => $department->id,
        'reason_for_contact' => 'Acceptance path second opinion',
        'diagnosis' => 'Demo complaint only',
        'symptoms_or_history' => 'Synthetic acceptance scenario',
        'documents' => [$file],
        'document_categories' => ['medical_report'],
        'enquiry_consent' => '1',
        'health_data_consent' => '1',
        'ai_processing_consent' => '1',
    ])->assertRedirect();

    $case = PatientCase::query()
        ->where('team_id', $team->id)
        ->whereHas('patient', fn ($query) => $query->where('email', 'acceptance.patient@example.test'))
        ->firstOrFail();

    expect($case->reference)->toStartWith('CF-');

    $document = PatientDocument::query()
        ->where('patient_case_id', $case->id)
        ->firstOrFail();

    expect(Storage::disk($document->disk)->exists($document->path))->toBeTrue()
        ->and($document->processing_status)->toBe(DocumentProcessingStatus::NeedsReview)
        ->and($document->currentExtraction?->is_synthetic)->toBeTrue()
        ->and($case->fresh()->status)->toBe(CaseStatus::AwaitingCoordinatorReview);

    // Coordinator can manage case assignment and status.
    $this->actingAs($coordinator)
        ->patch(route('organizations.cases.assignment.update', [
            'current_team' => $team,
            'case' => $case,
        ]), [
            'coordinator_id' => $coordinator->id,
            'doctor_id' => $doctor->id,
        ])
        ->assertRedirect();

    $this->actingAs($coordinator)
        ->patch(route('organizations.cases.status.update', [
            'current_team' => $team,
            'case' => $case,
        ]), [
            'status' => CaseStatus::AwaitingDoctorReview->value,
            'note' => 'Ready for clinician review',
        ])
        ->assertRedirect();

    expect($case->fresh()->status)->toBe(CaseStatus::AwaitingDoctorReview)
        ->and($case->fresh()->assigned_doctor_id)->toBe($doctor->id);

    // Unauthorized outsider cannot access documents.
    $this->actingAs($outsider)
        ->get(route('organizations.documents.show', [
            'current_team' => $team,
            'case' => $case,
            'document' => $document,
        ]))
        ->assertForbidden();

    // Doctor reviews AI comparison workspace and records human clinical evaluation.
    $this->actingAs($doctor)
        ->get(route('organizations.documents.show', [
            'current_team' => $team,
            'case' => $case,
            'document' => $document,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('organizations/documents/show')
            ->where('ai.extraction.is_synthetic', true));

    $this->actingAs($doctor)
        ->post(route('organizations.clinical.observations.store', [
            'current_team' => $team,
            'case' => $case,
        ]), [
            'body' => 'Human clinical observation for acceptance path.',
            'visibility' => 'coordinator_shared',
        ])
        ->assertRedirect();

    $this->actingAs($doctor)
        ->post(route('organizations.clinical.recommendations.store', [
            'current_team' => $team,
            'case' => $case,
        ]), [
            'recommendation_text' => 'Human-authored acceptance recommendation.',
            'submit_for_approval' => true,
            'supporting_document_uuids' => [$document->uuid],
        ])
        ->assertRedirect();

    $recommendationUuid = $case->fresh()->clinicalRecommendations()->where('is_current', true)->value('uuid');

    $this->actingAs($doctor)
        ->post(route('organizations.clinical.approvals.store', [
            'current_team' => $team,
            'case' => $case,
            'recommendation' => $recommendationUuid,
        ]), [
            'outcome' => ClinicalApprovalOutcome::Approved->value,
            'notes' => 'Explicit human approval',
        ])
        ->assertRedirect();

    $this->actingAs($doctor)
        ->post(route('organizations.clinical.reviews.store', [
            'current_team' => $team,
            'case' => $case,
        ]), [
            'outcome' => DoctorReviewOutcome::ReadyForProposal->value,
            'findings' => 'Human-reviewed acceptance evaluation complete.',
        ])
        ->assertRedirect();

    expect($case->fresh()->status)->toBe(CaseStatus::TreatmentProposalPreparation);

    // Dashboard metrics use live database counts for authorized staff.
    $this->actingAs($coordinator)
        ->get(route('dashboard', ['current_team' => $team]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('metrics.available', true)
            ->where('metrics.open_cases', fn ($count) => $count >= 1));
});

test('management viewers cannot open patient cases or clinical documents', function () {
    $dataset = app(CareFlowDemoDataset::class)->seed();
    $team = $dataset['team'];
    $viewer = $dataset['users']['viewer'];

    $case = PatientCase::query()
        ->where('team_id', $team->id)
        ->where('is_demo', true)
        ->whereHas('documents')
        ->firstOrFail();

    $document = $case->documents()->firstOrFail();

    $this->actingAs($viewer)
        ->get(route('organizations.cases.show', [
            'current_team' => $team,
            'case' => $case,
        ]))
        ->assertForbidden();

    $this->actingAs($viewer)
        ->get(route('organizations.documents.show', [
            'current_team' => $team,
            'case' => $case,
            'document' => $document,
        ]))
        ->assertForbidden();
});
