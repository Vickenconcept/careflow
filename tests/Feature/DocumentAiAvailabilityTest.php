<?php

use App\Enums\ConsentType;
use App\Enums\DocumentCategory;
use App\Enums\DocumentProcessingStatus;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\PatientConsent;
use App\Models\PatientDocument;
use App\Models\PatientEnquiry;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function availabilityDocument(User $owner, bool $aiEnabled, bool $aiConsent): array
{
    $team = $owner->currentTeam;
    $team->ensureOrganizationSetting()->update(['ai_processing_enabled' => $aiEnabled]);

    $patient = Patient::factory()->create(['team_id' => $team->id]);
    $enquiry = PatientEnquiry::factory()->create(['team_id' => $team->id, 'patient_id' => $patient->id]);
    $case = PatientCase::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_enquiry_id' => $enquiry->id,
    ]);

    if ($aiConsent) {
        PatientConsent::factory()->create([
            'team_id' => $team->id,
            'patient_id' => $patient->id,
            'consent_type' => ConsentType::AiProcessing,
        ]);
    }

    $uuid = (string) Str::uuid();
    $path = "teams/{$team->id}/cases/{$case->id}/documents/{$uuid}.pdf";
    Storage::disk('local')->put($path, '%PDF-test');

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
        'processing_error' => 'AI processing is switched off for this organization. Review the original file manually.',
    ]);

    return [$team, $case, $document];
}

test('the document page says why AI cannot run while it is switched off', function () {
    Storage::fake('local');
    $owner = User::factory()->create();
    [$team, $case, $document] = availabilityDocument($owner, aiEnabled: false, aiConsent: true);

    $this->actingAs($owner)
        ->get(route('organizations.documents.show', ['current_team' => $team, 'case' => $case, 'document' => $document]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('document.ai_available', false)
            ->where('document.ai_switched_off', true)
            ->where('document.ai_unavailable_reason', 'AI processing is switched off for this organization. Review the original file manually.'));
});

test('a document that arrived while AI was off can be analysed once AI is on', function () {
    Storage::fake('local');
    $owner = User::factory()->create();
    [$team, $case, $document] = availabilityDocument($owner, aiEnabled: true, aiConsent: true);

    $this->actingAs($owner)
        ->get(route('organizations.documents.show', ['current_team' => $team, 'case' => $case, 'document' => $document]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('document.ai_available', true)
            ->where('document.ai_unavailable_reason', null));
});

test('the case document list shows each document AI state and its actions', function () {
    Storage::fake('local');
    $owner = User::factory()->create();
    [$team, $case] = availabilityDocument($owner, aiEnabled: true, aiConsent: false);

    $this->actingAs($owner)
        ->get(route('organizations.cases.show', ['current_team' => $team, 'case' => $case]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('case.documents.0.ai_state', 'unavailable')
            ->where('case.documents.0.download_url', fn ($url) => str_contains((string) $url, '/download')));
});
