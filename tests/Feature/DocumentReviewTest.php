<?php

use App\Enums\CaseStatus;
use App\Enums\DocumentCategory;
use App\Enums\DocumentProcessingStatus;
use App\Enums\TeamRole;
use App\Models\DocumentExtraction;
use App\Models\DocumentProcessingRun;
use App\Models\DocumentReviewEvent;
use App\Models\DocumentSummary;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\PatientDocument;
use App\Models\PatientEnquiry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
});

function seededReviewDocument(Team $team): array
{
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
        'reference' => 'CF-2026-710001',
    ]);

    $uuid = (string) Str::uuid();
    $path = "teams/{$team->id}/cases/{$case->id}/documents/{$uuid}.pdf";
    Storage::disk('local')->put($path, '%PDF-demo');

    $document = PatientDocument::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_case_id' => $case->id,
        'uuid' => $uuid,
        'disk' => 'local',
        'path' => $path,
        'extension' => 'pdf',
        'category' => DocumentCategory::MedicalReport,
        'processing_status' => DocumentProcessingStatus::NeedsReview,
    ]);

    $run = DocumentProcessingRun::factory()->create([
        'team_id' => $team->id,
        'patient_document_id' => $document->id,
        'status' => DocumentProcessingStatus::NeedsReview,
    ]);

    DocumentExtraction::factory()->create([
        'team_id' => $team->id,
        'patient_document_id' => $document->id,
        'document_processing_run_id' => $run->id,
        'version' => 1,
        'is_current' => true,
        'payload' => [
            'diagnoses_as_documented' => [],
            'uncertain_sections' => [['field' => 'all', 'reason' => 'mock']],
        ],
    ]);

    DocumentSummary::factory()->create([
        'team_id' => $team->id,
        'patient_document_id' => $document->id,
        'document_processing_run_id' => $run->id,
        'version' => 1,
        'is_current' => true,
        'sections' => [
            'document_overview' => 'Mock overview',
        ],
    ]);

    return [$case, $document];
}

test('authorized staff can open the document comparison workspace', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    [$case, $document] = seededReviewDocument($team);

    $this->actingAs($owner)
        ->get(route('organizations.documents.show', [
            'current_team' => $team,
            'case' => $case,
            'document' => $document,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('organizations/documents/show')
            ->where('document.uuid', $document->uuid)
            ->where('ai.summary.sections.document_overview', 'Mock overview')
            ->where('ai.disclaimer_summary', 'AI-generated draft. Requires review by an authorized healthcare professional.'));
});

test('reviewers can correct extraction without overwriting the original payload', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    [$case, $document] = seededReviewDocument($team);

    $this->actingAs($owner)
        ->patch(route('organizations.documents.review.update', [
            'current_team' => $team,
            'case' => $case,
            'document' => $document,
        ]), [
            'corrected_payload' => [
                'diagnoses_as_documented' => [
                    ['value' => 'Human-corrected demo diagnosis', 'review_status' => 'corrected'],
                ],
            ],
            'corrected_sections' => [
                'document_overview' => 'Human-reviewed overview',
            ],
            'reason' => 'Clarified after reading source',
        ])
        ->assertRedirect();

    $extraction = DocumentExtraction::query()
        ->where('patient_document_id', $document->id)
        ->where('is_current', true)
        ->firstOrFail();

    expect($extraction->payload['diagnoses_as_documented'])->toBe([])
        ->and($extraction->corrected_payload['diagnoses_as_documented'][0]['value'])
        ->toBe('Human-corrected demo diagnosis')
        ->and($extraction->review_status)->toBe('corrected');

    expect(DocumentReviewEvent::query()->where('patient_document_id', $document->id)->count())->toBe(2);
});

test('doctors without document assignment cannot review unassigned case documents', function () {
    $owner = User::factory()->create();
    $doctor = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($doctor, ['role' => TeamRole::Doctor->value]);
    $doctor->switchTeam($team);

    [$case, $document] = seededReviewDocument($team);

    $this->actingAs($doctor)
        ->get(route('organizations.documents.show', [
            'current_team' => $team,
            'case' => $case,
            'document' => $document,
        ]))
        ->assertForbidden();
});
