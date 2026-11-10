<?php

use App\Contracts\Documents\DocumentClassifier;
use App\Contracts\Documents\DocumentLanguageDetector;
use App\Contracts\Documents\DocumentSummarizer;
use App\Contracts\Documents\DocumentTextExtractor;
use App\Contracts\Documents\DocumentTranslator;
use App\Contracts\Documents\MedicalInformationExtractor;
use App\Domains\Documents\Providers\Mock\MockDocumentLanguageDetector;
use App\Domains\Documents\Providers\OpenAi\OpenAiDocumentLanguageDetector;
use App\Domains\Documents\Providers\OpenAi\OpenAiDocumentTextExtractor;
use App\Enums\DocumentCategory;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\PatientDocument;
use App\Models\PatientEnquiry;
use App\Models\Team;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

test('automated tests bind the mock document AI provider via phpunit', function () {
    expect(config('careflow.ai.provider'))->toBe('mock')
        ->and(app(DocumentLanguageDetector::class))->toBeInstanceOf(MockDocumentLanguageDetector::class);
});

test('openai provider bindings resolve when an API key is configured', function () {
    config([
        'careflow.ai.provider' => 'openai',
        'ai.providers.openai.key' => 'sk-test-key',
    ]);

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
