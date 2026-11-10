<?php

use App\Enums\CaseStatus;
use App\Enums\DocumentCategory;
use App\Enums\DocumentProcessingStatus;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\PatientDocument;
use App\Models\PatientEnquiry;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
});

test('document workspace exposes an inline preview url separate from download', function () {
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
        'reference' => 'CF-2026-720001',
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
        'processing_status' => DocumentProcessingStatus::NeedsReview,
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
            ->where('document.preview_url', route('organizations.documents.preview', [
                'current_team' => $team->slug,
                'case' => $case->reference,
                'document' => $document->uuid,
            ]))
            ->where('document.download_url', route('organizations.documents.download', [
                'current_team' => $team->slug,
                'case' => $case->reference,
                'document' => $document->uuid,
            ]))
            ->where('case.status', $case->status->value)
            ->where('permissions.canUpdateStatus', true)
            ->has('availableStatusTransitions')
            ->where('case.status_update_url', route('organizations.cases.status.update', [
                'current_team' => $team->slug,
                'case' => $case->reference,
            ])));
});

test('preview streams the file inline while download forces attachment', function () {
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
        'reference' => 'CF-2026-720002',
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
        'processing_status' => DocumentProcessingStatus::NeedsReview,
    ]);

    $preview = $this->actingAs($owner)
        ->get(route('organizations.documents.preview', [
            'current_team' => $team,
            'case' => $case,
            'document' => $document,
        ]))
        ->assertOk();

    expect(strtolower((string) $preview->headers->get('content-disposition')))
        ->toContain('inline')
        ->and(strtolower((string) $preview->headers->get('content-type')))
        ->toContain('pdf');

    $download = $this->actingAs($owner)
        ->get(route('organizations.documents.download', [
            'current_team' => $team,
            'case' => $case,
            'document' => $document,
        ]))
        ->assertOk();

    expect(strtolower((string) $download->headers->get('content-disposition')))
        ->toContain('attachment');
});
