<?php

use App\Ai\Agents\DocumentVisionTextExtractionAgent;
use App\Contracts\Documents\DocumentClassifier;
use App\Contracts\Documents\DocumentLanguageDetector;
use App\Contracts\Documents\DocumentSummarizer;
use App\Contracts\Documents\DocumentTextExtractor;
use App\Contracts\Documents\DocumentTranslator;
use App\Contracts\Documents\MedicalInformationExtractor;
use App\Domains\Documents\Providers\OpenAi\OpenAiDocumentLanguageDetector;
use App\Domains\Documents\Providers\OpenAi\OpenAiDocumentTextExtractor;
use App\Enums\DocumentCategory;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\PatientDocument;
use App\Models\PatientEnquiry;
use App\Models\Team;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\Ai\MockDocumentLanguageDetector;

test('automated tests swap in document AI doubles that ship only with the test suite', function () {
    expect(class_exists('App\Domains\Documents\Providers\Mock\MockDocumentLanguageDetector'))->toBeFalse()
        ->and(app(DocumentLanguageDetector::class))->toBeInstanceOf(MockDocumentLanguageDetector::class);
});

test('openai provider bindings resolve when an API key is configured', function () {
    config([
        'careflow.ai.provider' => 'openai',
        'ai.providers.openai.key' => 'sk-test-key',
    ]);

    (new AppServiceProvider(app()))->register();
    app()->forgetInstance(DocumentLanguageDetector::class);
    app()->forgetInstance(DocumentTextExtractor::class);

    expect(app(DocumentLanguageDetector::class))->toBeInstanceOf(OpenAiDocumentLanguageDetector::class)
        ->and(app(DocumentTextExtractor::class))->toBeInstanceOf(OpenAiDocumentTextExtractor::class)
        ->and(app(DocumentClassifier::class))->not->toBeInstanceOf(MockDocumentLanguageDetector::class)
        ->and(app(MedicalInformationExtractor::class))->not->toBeNull()
        ->and(app(DocumentTranslator::class))->not->toBeNull()
        ->and(app(DocumentSummarizer::class))->not->toBeNull();
});

test('openai provider refuses to bind without an API key', function () {
    config([
        'careflow.ai.provider' => 'openai',
        'ai.providers.openai.key' => '',
    ]);

    (new AppServiceProvider(app()))->register();
    app()->forgetInstance(DocumentLanguageDetector::class);

    expect(fn () => app(DocumentLanguageDetector::class))
        ->toThrow(RuntimeException::class, 'OPENAI_API_KEY');
});

test('openai text extractor returns non-synthetic readable source text', function () {
    config([
        'careflow.ai.provider' => 'openai',
        'ai.providers.openai.key' => 'sk-test-key',
    ]);

    Storage::fake('local');

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

    $path = 'documents/'.$team->id.'/readable.txt';
    Storage::disk('local')->put($path, "Patient report\nReported diagnosis: Hypertension");

    $document = PatientDocument::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_case_id' => $case->id,
        'disk' => 'local',
        'path' => $path,
        'original_filename' => 'readable.txt',
        'extension' => 'txt',
        'category' => DocumentCategory::MedicalReport,
        'uuid' => (string) Str::uuid(),
    ]);

    $result = app(OpenAiDocumentTextExtractor::class)->extract($document);

    expect($result->provider)->toBe('openai')
        ->and($result->isSynthetic)->toBeFalse()
        ->and($result->text)->toContain('Hypertension');
});

test('openai text extractor never treats raw pdf syntax as the document text', function () {
    config([
        'careflow.ai.provider' => 'openai',
        'ai.providers.openai.key' => 'sk-test-key',
    ]);

    Storage::fake('local');
    DocumentVisionTextExtractionAgent::fake([[
        'extracted_text' => 'Diagnosis: Grade 4 osteoarthritis of the right knee.',
        'is_partial' => false,
        'warnings' => [],
    ]]);

    $team = Team::factory()->create();
    $patient = Patient::factory()->create(['team_id' => $team->id]);
    $enquiry = PatientEnquiry::factory()->create(['team_id' => $team->id, 'patient_id' => $patient->id]);
    $case = PatientCase::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_enquiry_id' => $enquiry->id,
    ]);

    $path = 'documents/'.$team->id.'/uncompressed.pdf';
    Storage::disk('local')->put($path, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\nBT (Diagnosis) Tj ET\n%%EOF");

    $document = PatientDocument::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_case_id' => $case->id,
        'disk' => 'local',
        'path' => $path,
        'original_filename' => 'uncompressed.pdf',
        'extension' => 'pdf',
        'mime_type' => 'application/pdf',
        'category' => DocumentCategory::MedicalReport,
        'uuid' => (string) Str::uuid(),
    ]);

    $result = app(OpenAiDocumentTextExtractor::class)->extract($document);

    expect($result->text)->toBe('Diagnosis: Grade 4 osteoarthritis of the right knee.')
        ->and($result->text)->not->toContain('%PDF');
    DocumentVisionTextExtractionAgent::assertPromptedTimes(1);
});
