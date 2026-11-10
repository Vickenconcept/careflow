<?php

namespace App\Jobs\Patients;

use App\Enums\DocumentProcessingStatus;
use App\Models\PatientDocument;
use App\Services\Documents\DocumentProcessingPipeline;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ProcessPatientDocument implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 30, 120];

    public int $timeout = 240;

    public int $uniqueFor = 3600;

    public function __construct(public int $documentId) {}

    public function uniqueId(): string
    {
        return 'process-patient-document:'.$this->documentId;
    }

    public function handle(): void
    {
        $document = PatientDocument::query()->find($this->documentId);

        if ($document === null) {
            return;
        }

        if (in_array($document->processing_status, [
            DocumentProcessingStatus::Completed,
            DocumentProcessingStatus::Cancelled,
        ], true)) {
            return;
        }

        if (
            strtolower((string) config('careflow.ai.provider')) === 'openai'
            && blank(config('ai.providers.openai.key'))
        ) {
            $document->update([
                'processing_status' => DocumentProcessingStatus::Failed,
                'processing_error' => 'OPENAI_API_KEY is missing. Add it to .env, restart the queue worker, then run AI again.',
            ]);

            return;
        }

        // Resolve after the key check so missing OPENAI_API_KEY fails cleanly
        // instead of exploding while the container injects OpenAI adapters.
        app(DocumentProcessingPipeline::class)->process($document);
    }

    public function failed(?Throwable $exception): void
    {
        $document = PatientDocument::query()->find($this->documentId);

        if ($document === null) {
            return;
        }

        $message = trim((string) $exception?->getMessage());

        if ($message !== '' && preg_match('/"message"\s*:\s*"([^"]+)"/', $message, $matches) === 1) {
            $message = $matches[1];
        }

        $document->update([
            'processing_status' => DocumentProcessingStatus::Failed,
            'processing_error' => $message !== ''
                ? Str::limit(preg_replace('/\s+/', ' ', $message) ?? $message, 240)
                : 'Document processing failed after retries.',
        ]);

        Log::warning('Patient document processing failed', [
            'document_id' => $this->documentId,
            'team_id' => $document->team_id,
            'case_id' => $document->patient_case_id,
            'exception' => $exception?->getMessage(),
        ]);
    }
}
