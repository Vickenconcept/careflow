<?php

use App\Enums\CaseStatus;
use App\Enums\DocumentCategory;
use App\Enums\DocumentProcessingStatus;
use App\Enums\TeamRole;
use App\Jobs\Patients\ProcessPatientDocument;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\PatientDocument;
use App\Models\PatientEnquiry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();
});

test('document workspace exposes live processing progress props', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;

    $patient = Patient::factory()->create(['team_id' => $team->id]);
    $enquiry = PatientEnquiry::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
    ]);
    $case = PatientCase::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_enquiry_id' => $enquiry->id,
        'status' => CaseStatus::DocumentsReceived,
        'reference' => 'CF-2026-730001',
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
        'original_filename' => 'report.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'category' => DocumentCategory::MedicalReport,
        'processing_status' => DocumentProcessingStatus::Extracting,
    ]);

    $this->actingAs($owner)
        ->get(route('organizations.documents.show', [
            'current_team' => $team,
            'case' => $case,
            'document' => $document,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('organizations/documents/show')
            ->where('document.processing_status', 'extracting')
            ->where('document.processing_in_progress', true)
            ->has('document.processing_steps')
            ->where('ai.provider', 'mock')
            ->where('permissions.canReprocess', true));
});

test('staff with manage documents permission can requeue ai processing', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;

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
        'reference' => 'CF-2026-730002',
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
        'original_filename' => 'report.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'category' => DocumentCategory::MedicalReport,
        'processing_status' => DocumentProcessingStatus::Failed,
        'processing_error' => 'Document processing failed.',
    ]);

    $this->actingAs($owner)
        ->post(route('organizations.documents.reprocess', [
            'current_team' => $team,
            'case' => $case,
            'document' => $document,
        ]))
        ->assertRedirect();

    expect($document->fresh()->processing_status)->toBe(DocumentProcessingStatus::Pending)
        ->and($document->fresh()->processing_error)->toBeNull();

    Queue::assertPushed(ProcessPatientDocument::class, fn ($job) => $job->documentId === $document->id);
});

test('viewers cannot requeue document ai processing', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($viewer, ['role' => TeamRole::ManagementViewer->value]);
    $viewer->switchTeam($team);

    $patient = Patient::factory()->create(['team_id' => $team->id]);
    $enquiry = PatientEnquiry::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
    ]);
    $case = PatientCase::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_enquiry_id' => $enquiry->id,
        'reference' => 'CF-2026-730003',
    ]);

    $document = PatientDocument::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_case_id' => $case->id,
        'processing_status' => DocumentProcessingStatus::Failed,
    ]);

    $this->actingAs($viewer)
        ->post(route('organizations.documents.reprocess', [
            'current_team' => $team,
            'case' => $case,
            'document' => $document,
        ]))
        ->assertForbidden();

    Queue::assertNothingPushed();
});
