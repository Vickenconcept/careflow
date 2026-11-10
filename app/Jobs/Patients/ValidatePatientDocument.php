<?php

namespace App\Jobs\Patients;

use App\Enums\DocumentProcessingStatus;
use App\Models\PatientDocument;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ValidatePatientDocument implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 30, 120];

    public int $uniqueFor = 3600;

    public function __construct(public int $documentId) {}

    public function uniqueId(): string
    {
        return 'validate-patient-document:'.$this->documentId;
    }

    /**
     * Validate file presence and metadata only.
     *
     * AI extraction / OCR providers must be plugged in behind interfaces later.
     * This job must remain safe to retry and must not invent clinical content.
     */
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

        $document->update([
            'processing_status' => DocumentProcessingStatus::Validating,
            'processing_error' => null,
        ]);

        if (! Storage::disk($document->disk)->exists($document->path)) {
            $document->update([
                'processing_status' => DocumentProcessingStatus::Failed,
                'processing_error' => 'Stored file is missing from private storage.',
            ]);

            return;
        }

        $allowed = config('careflow.documents.allowed_mimes', []);
        $extension = strtolower($document->extension);

        if (! in_array($extension, $allowed, true)) {
            $document->update([
                'processing_status' => DocumentProcessingStatus::Failed,
                'processing_error' => 'Unsupported document type.',
            ]);

            return;
        }

        // Mock pipeline stop-point: file is validated and waiting for AI adapters.
        $document->update([
            'processing_status' => DocumentProcessingStatus::NeedsReview,
            'processing_error' => null,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $document = PatientDocument::query()->find($this->documentId);

        if ($document === null) {
            return;
        }

        $document->update([
            'processing_status' => DocumentProcessingStatus::Failed,
            'processing_error' => 'Document validation failed after retries.',
        ]);

        Log::warning('Patient document validation failed', [
            'document_id' => $this->documentId,
            'team_id' => $document->team_id,
            'case_id' => $document->patient_case_id,
            'exception' => $exception?->getMessage(),
        ]);
    }
}
