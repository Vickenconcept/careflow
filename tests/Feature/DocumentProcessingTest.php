<?php

use App\Contracts\Documents\DocumentTranslator;
use App\Domains\Documents\Data\TranslationResult;
use App\Enums\CaseStatus;
use App\Enums\ConsentType;
use App\Enums\DocumentCategory;
use App\Enums\DocumentProcessingStatus;
use App\Jobs\Patients\ProcessPatientDocument;
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
use App\Models\Team;
use App\Services\Documents\DocumentProcessingPipeline;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('local');
});

test('mock document processing pipeline stores versioned synthetic outputs', function () {
    $team = Team::factory()->create();
    $team->ensureOrganizationSetting()->update([
        'default_language' => 'en',
        'ai_processing_enabled' => true,
    ]);

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
        'reference' => 'CF-2026-700001',
    ]);

    $uuid = (string) Str::uuid();
    $path = "teams/{$team->id}/cases/{$case->id}/documents/{$uuid}.txt";
    Storage::disk('local')->put($path, "Reported diagnosis: Demo arrhythmia suspicion\nPatient complaint: palpitations");

    PatientConsent::factory()->create(['team_id' => $team->id, 'patient_id' => $patient->id, 'consent_type' => ConsentType::AiProcessing]);

    $document = PatientDocument::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_case_id' => $case->id,
        'uuid' => $uuid,
        'original_filename' => 'cardiac-report.txt',
        'disk' => 'local',
        'path' => $path,
        'mime_type' => 'text/plain',
        'extension' => 'txt',
        'category' => DocumentCategory::MedicalReport,
        'processing_status' => DocumentProcessingStatus::Pending,
    ]);

    // Allow txt for this focused pipeline test.
    config(['careflow.documents.allowed_mimes' => ['txt', 'pdf', 'jpg', 'jpeg', 'png']]);

    (new ProcessPatientDocument($document->id))->handle();

    $document->refresh();
    $case->refresh();

    expect($document->processing_status)->toBe(DocumentProcessingStatus::NeedsReview)
        ->and($document->original_language)->not->toBeNull()
        ->and($case->status)->toBe(CaseStatus::AwaitingCoordinatorReview);

    expect(DocumentExtractedText::query()->where('patient_document_id', $document->id)->where('is_current', true)->exists())->toBeTrue()
        ->and(DocumentExtraction::query()->where('patient_document_id', $document->id)->where('is_current', true)->exists())->toBeTrue()
        ->and(DocumentTranslation::query()->where('patient_document_id', $document->id)->where('is_current', true)->exists())->toBeTrue()
        ->and(DocumentSummary::query()->where('patient_document_id', $document->id)->where('is_current', true)->exists())->toBeTrue();

    $extraction = DocumentExtraction::query()
        ->where('patient_document_id', $document->id)
        ->where('is_current', true)
        ->firstOrFail();

    expect($extraction->is_synthetic)->toBeTrue()
        ->and($extraction->payload['uncertain_sections'])->not->toBeEmpty();
});

test('a failing ai step shows staff a plain message and marks the run failed', function () {
    $team = Team::factory()->create();
    $team->ensureOrganizationSetting()->update(['ai_processing_enabled' => true]);
    $patient = Patient::factory()->create(['team_id' => $team->id]);
    $case = PatientCase::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_enquiry_id' => PatientEnquiry::factory()->create(['team_id' => $team->id, 'patient_id' => $patient->id])->id,
    ]);
    PatientConsent::factory()->create(['team_id' => $team->id, 'patient_id' => $patient->id, 'consent_type' => ConsentType::AiProcessing]);

    $path = "teams/{$team->id}/cases/{$case->id}/documents/report.pdf";
    Storage::disk('local')->put($path, '%PDF-demo');
    $document = PatientDocument::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_case_id' => $case->id,
        'disk' => 'local',
        'path' => $path,
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'processing_status' => DocumentProcessingStatus::Pending,
    ]);

    app()->bind(DocumentTranslator::class, fn () => new class implements DocumentTranslator
    {
        public function translate(string $text, string $sourceLanguage, string $targetLanguage): TranslationResult
        {
            throw new RuntimeException('HTTP 429 {"error":{"message":"Rate limit reached for gpt-4o"}}');
        }
    });

    expect(fn () => app(DocumentProcessingPipeline::class)->process($document))->toThrow(RuntimeException::class);

    $document->refresh();
    $runs = DocumentProcessingRun::query()->where('patient_document_id', $document->id)->get();

    expect($document->processing_status)->toBe(DocumentProcessingStatus::Failed)
        ->and($document->processing_error)->toBe(DocumentProcessingPipeline::GENERIC_FAILURE_MESSAGE)
        ->and($document->processing_error)->not->toContain('gpt')
        ->and($runs)->toHaveCount(1)
        ->and($runs->first()->status)->toBe(DocumentProcessingStatus::Failed);
});
