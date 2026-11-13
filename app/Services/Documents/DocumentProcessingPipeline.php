<?php

namespace App\Services\Documents;

use App\Actions\Patients\RecordCaseActivity;
use App\Contracts\Documents\DocumentClassifier;
use App\Contracts\Documents\DocumentLanguageDetector;
use App\Contracts\Documents\DocumentSummarizer;
use App\Contracts\Documents\DocumentTextExtractor;
use App\Contracts\Documents\DocumentTranslator;
use App\Contracts\Documents\MedicalInformationExtractor;
use App\Enums\CaseActivityType;
use App\Enums\CaseStatus;
use App\Enums\DocumentProcessingStatus;
use App\Events\Patients\DocumentProcessingCompleted;
use App\Models\DocumentExtractedText;
use App\Models\DocumentExtraction;
use App\Models\DocumentProcessingRun;
use App\Models\DocumentSummary;
use App\Models\DocumentTranslation;
use App\Models\PatientDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DocumentProcessingPipeline
{
    public const MISSING_FILE_MESSAGE = 'The uploaded file could not be found. Please upload it again.';

    public const UNSUPPORTED_TYPE_MESSAGE = 'AI assist cannot read this type of file. You can still open the original and continue the case.';

    public const GENERIC_FAILURE_MESSAGE = 'AI assist could not read this document. You can still open the original file and continue the case.';

    public function __construct(
        private DocumentTextExtractor $textExtractor,
        private DocumentLanguageDetector $languageDetector,
        private DocumentClassifier $classifier,
        private MedicalInformationExtractor $informationExtractor,
        private DocumentTranslator $translator,
        private DocumentSummarizer $summarizer,
        private RecordCaseActivity $recordActivity,
    ) {}

    /**
     * Run the AI pipeline for a document.
     *
     * Each step is saved straight away so staff see live progress, and the slow
     * AI calls run outside any database transaction. Holding one transaction
     * across them locks SQLite and stalls every other request, including the
     * document page's status polling, until the run finishes. Only the final
     * write of the outputs is wrapped in a transaction.
     */
    public function process(PatientDocument $document): DocumentProcessingRun
    {
        $run = null;

        try {
            /** @var PatientDocument $locked */
            $locked = PatientDocument::query()->whereKey($document->id)->firstOrFail();

            if (in_array($locked->processing_status, [
                DocumentProcessingStatus::Completed,
                DocumentProcessingStatus::Cancelled,
            ], true)) {
                return $locked->processingRuns()->latest('id')->firstOrFail();
            }

            $attempt = ((int) $locked->processingRuns()->max('attempt')) + 1;

            $run = DocumentProcessingRun::query()->create([
                'team_id' => $locked->team_id,
                'patient_document_id' => $locked->id,
                'uuid' => (string) Str::uuid(),
                'provider' => (string) config('careflow.ai.provider', 'openai'),
                'status' => DocumentProcessingStatus::Validating,
                'attempt' => max(1, $attempt),
                'started_at' => now(),
            ]);

            $locked->update(['processing_status' => DocumentProcessingStatus::Validating]);
            $locked->loadMissing(['team.organizationSetting', 'patient', 'case']);

            $this->assertFileIsValid($locked);

            $this->advance($locked, $run, DocumentProcessingStatus::Extracting);
            $textResult = $this->textExtractor->extract($locked);

            $this->advance($locked, $run, DocumentProcessingStatus::DetectingLanguage);
            $language = $this->languageDetector->detect($textResult->text);

            $classification = $this->classifier->classify($locked, $textResult->text);

            $locked->update([
                'category' => $classification->category,
                'original_language' => $language->language,
            ]);
            $this->advance($locked, $run, DocumentProcessingStatus::Structuring);

            $structured = $this->informationExtractor->extract(
                $locked,
                $textResult->text,
                $language->language,
            );

            $targetLanguage = $locked->team->organizationSetting?->default_language
                ?: $locked->patient?->preferred_language
                ?: 'en';

            $this->advance($locked, $run, DocumentProcessingStatus::Translating);
            $translation = $this->translator->translate(
                $textResult->text,
                $language->language,
                $targetLanguage,
            );

            $this->advance($locked, $run, DocumentProcessingStatus::Summarizing);
            $summary = $this->summarizer->summarize($locked, $textResult->text, $structured);

            return DB::transaction(function () use ($locked, $run, $textResult, $structured, $translation, $summary) {
                $this->persistVersionedOutputs(
                    $locked,
                    $run,
                    $textResult->text,
                    $textResult->provider,
                    $textResult->isSynthetic,
                    $textResult->warnings,
                    $structured->payload,
                    $structured->schemaVersion,
                    $structured->provider,
                    $structured->isSynthetic,
                    $translation->translatedText,
                    $translation->sourceLanguage,
                    $translation->targetLanguage,
                    $translation->provider,
                    $translation->disclaimer,
                    $translation->isSynthetic,
                    $summary->sections,
                    $summary->provider,
                    $summary->disclaimer,
                    $summary->isSynthetic,
                );

                $locked->update([
                    'processing_status' => DocumentProcessingStatus::NeedsReview,
                    'processing_error' => null,
                ]);

                $anySynthetic = $textResult->isSynthetic
                    || $structured->isSynthetic
                    || $translation->isSynthetic
                    || $summary->isSynthetic;

                $run->update([
                    'status' => DocumentProcessingStatus::NeedsReview,
                    'finished_at' => now(),
                    'usage' => [
                        'provider' => $run->provider,
                        'synthetic' => $anySynthetic,
                        'steps' => [
                            'extract',
                            'detect_language',
                            'classify',
                            'structure',
                            'translate',
                            'summarize',
                        ],
                    ],
                    'error_message' => null,
                ]);

                $this->touchCaseForReview($locked, $anySynthetic);

                DB::afterCommit(function () use ($locked): void {
                    event(new DocumentProcessingCompleted(
                        documentId: $locked->id,
                        caseId: (int) $locked->patient_case_id,
                        teamId: $locked->team_id,
                    ));
                });

                return $run->fresh();
            });
        } catch (Throwable $exception) {
            $message = $this->staffFacingProcessingError($exception);
            $fresh = PatientDocument::query()->find($document->id);

            if ($fresh !== null) {
                $fresh->update([
                    'processing_status' => DocumentProcessingStatus::Failed,
                    'processing_error' => $message,
                ]);

                $failure = [
                    'status' => DocumentProcessingStatus::Failed,
                    'finished_at' => now(),
                    'error_message' => $message,
                ];

                if ($run !== null) {
                    $run->update($failure);
                } else {
                    DocumentProcessingRun::query()->create([
                        ...$failure,
                        'team_id' => $fresh->team_id,
                        'patient_document_id' => $fresh->id,
                        'uuid' => (string) Str::uuid(),
                        'provider' => (string) config('careflow.ai.provider', 'openai'),
                        'attempt' => max(1, ((int) $fresh->processingRuns()->max('attempt')) + 1),
                        'started_at' => now(),
                    ]);
                }
            }

            throw $exception;
        }
    }

    /**
     * Save the next pipeline step so the document page can show it straight away.
     */
    protected function advance(PatientDocument $document, DocumentProcessingRun $run, DocumentProcessingStatus $status): void
    {
        $document->update(['processing_status' => $status]);
        $run->update(['status' => $status]);
    }

    protected function assertFileIsValid(PatientDocument $document): void
    {
        if (! $document->filesystem()->exists($document->path)) {
            throw new RuntimeException(self::MISSING_FILE_MESSAGE);
        }

        $allowed = config('careflow.documents.allowed_mimes', []);

        if (! in_array(strtolower($document->extension), $allowed, true)) {
            throw new RuntimeException(self::UNSUPPORTED_TYPE_MESSAGE);
        }
    }

    /**
     * @param  list<string>  $warnings
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $sections
     */
    protected function persistVersionedOutputs(
        PatientDocument $document,
        DocumentProcessingRun $run,
        string $extractedText,
        string $textProvider,
        bool $textSynthetic,
        array $warnings,
        array $payload,
        string $schemaVersion,
        string $extractionProvider,
        bool $extractionSynthetic,
        string $translatedText,
        string $sourceLanguage,
        string $targetLanguage,
        string $translationProvider,
        string $translationDisclaimer,
        bool $translationSynthetic,
        array $sections,
        string $summaryProvider,
        string $summaryDisclaimer,
        bool $summarySynthetic,
    ): void {
        DocumentExtractedText::query()
            ->where('patient_document_id', $document->id)
            ->where('is_current', true)
            ->update(['is_current' => false]);

        DocumentExtraction::query()
            ->where('patient_document_id', $document->id)
            ->where('is_current', true)
            ->update(['is_current' => false]);

        DocumentTranslation::query()
            ->where('patient_document_id', $document->id)
            ->where('is_current', true)
            ->update(['is_current' => false]);

        DocumentSummary::query()
            ->where('patient_document_id', $document->id)
            ->where('is_current', true)
            ->update(['is_current' => false]);

        $nextTextVersion = ((int) DocumentExtractedText::query()->where('patient_document_id', $document->id)->max('version')) + 1;
        $nextExtractionVersion = ((int) DocumentExtraction::query()->where('patient_document_id', $document->id)->max('version')) + 1;
        $nextTranslationVersion = ((int) DocumentTranslation::query()->where('patient_document_id', $document->id)->max('version')) + 1;
        $nextSummaryVersion = ((int) DocumentSummary::query()->where('patient_document_id', $document->id)->max('version')) + 1;

        DocumentExtractedText::query()->create([
            'team_id' => $document->team_id,
            'patient_document_id' => $document->id,
            'document_processing_run_id' => $run->id,
            'version' => max(1, $nextTextVersion),
            'extracted_text' => $extractedText,
            'provider' => $textProvider,
            'is_synthetic' => $textSynthetic,
            'warnings' => $warnings,
            'is_current' => true,
        ]);

        DocumentExtraction::query()->create([
            'team_id' => $document->team_id,
            'patient_document_id' => $document->id,
            'document_processing_run_id' => $run->id,
            'version' => max(1, $nextExtractionVersion),
            'schema_version' => $schemaVersion,
            'payload' => $payload,
            'provider' => $extractionProvider,
            'is_synthetic' => $extractionSynthetic,
            'review_status' => 'unreviewed',
            'is_current' => true,
        ]);

        DocumentTranslation::query()->create([
            'team_id' => $document->team_id,
            'patient_document_id' => $document->id,
            'document_processing_run_id' => $run->id,
            'version' => max(1, $nextTranslationVersion),
            'source_language' => $sourceLanguage,
            'target_language' => $targetLanguage,
            'translated_text' => $translatedText,
            'provider' => $translationProvider,
            'status' => 'completed',
            'disclaimer' => $translationDisclaimer,
            'is_synthetic' => $translationSynthetic,
            'is_current' => true,
            'translated_at' => now(),
        ]);

        DocumentSummary::query()->create([
            'team_id' => $document->team_id,
            'patient_document_id' => $document->id,
            'document_processing_run_id' => $run->id,
            'version' => max(1, $nextSummaryVersion),
            'sections' => $sections,
            'provider' => $summaryProvider,
            'disclaimer' => $summaryDisclaimer,
            'is_synthetic' => $summarySynthetic,
            'review_status' => 'unreviewed',
            'is_current' => true,
        ]);
    }

    protected function touchCaseForReview(PatientDocument $document, bool $synthetic): void
    {
        $case = $document->case()->lockForUpdate()->first();

        if ($case === null) {
            return;
        }

        if (in_array($case->status, [
            CaseStatus::NewEnquiry,
            CaseStatus::DocumentsReceived,
            CaseStatus::AiProcessing,
        ], true)) {
            $case->status = CaseStatus::AwaitingCoordinatorReview;
        }

        $case->last_activity_at = now();
        $case->save();

        $this->recordActivity->handle(
            case: $case,
            type: CaseActivityType::NoteAdded,
            summary: 'AI document processing completed and awaits human review',
            properties: [
                'document_uuid' => $document->uuid,
                'provider' => config('careflow.ai.provider', 'openai'),
                'synthetic' => $synthetic,
            ],
        );
    }

    /**
     * Plain message for hospital staff. Technical details go to the log, never the page.
     */
    protected function staffFacingProcessingError(Throwable $exception): string
    {
        $message = trim($exception->getMessage());

        if (in_array($message, [self::MISSING_FILE_MESSAGE, self::UNSUPPORTED_TYPE_MESSAGE], true)) {
            return $message;
        }

        Log::warning('Document AI pipeline failed', [
            'exception' => $exception::class,
            'message' => Str::limit($message, 1000),
        ]);

        return self::GENERIC_FAILURE_MESSAGE;
    }
}
