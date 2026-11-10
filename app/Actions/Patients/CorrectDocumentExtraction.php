<?php

namespace App\Actions\Patients;

use App\Models\DocumentReviewEvent;
use App\Models\PatientDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CorrectDocumentExtraction
{
    /**
     * @param  array<string, mixed>|null  $correctedPayload
     * @param  array<string, mixed>|null  $correctedSections
     */
    public function handle(
        PatientDocument $document,
        User $actor,
        ?array $correctedPayload = null,
        ?array $correctedSections = null,
        ?string $reason = null,
    ): PatientDocument {
        if ($correctedPayload === null && $correctedSections === null) {
            throw new InvalidArgumentException('No corrections were provided.');
        }

        return DB::transaction(function () use ($document, $actor, $correctedPayload, $correctedSections, $reason) {
            if ($correctedPayload !== null) {
                $extraction = $document->currentExtraction()->lockForUpdate()->firstOrFail();
                $previous = $extraction->effectivePayload();

                $extraction->update([
                    'corrected_payload' => $correctedPayload,
                    'review_status' => 'corrected',
                ]);

                DocumentReviewEvent::query()->create([
                    'team_id' => $document->team_id,
                    'patient_document_id' => $document->id,
                    'user_id' => $actor->id,
                    'target_type' => $extraction->getMorphClass(),
                    'target_id' => $extraction->id,
                    'field_path' => 'payload',
                    'previous_value' => $previous,
                    'new_value' => $correctedPayload,
                    'reason' => $reason,
                    'reviewed_at' => now(),
                ]);
            }

            if ($correctedSections !== null) {
                $summary = $document->currentSummary()->lockForUpdate()->firstOrFail();
                $previous = $summary->effectiveSections();

                $summary->update([
                    'corrected_sections' => $correctedSections,
                    'review_status' => 'corrected',
                ]);

                DocumentReviewEvent::query()->create([
                    'team_id' => $document->team_id,
                    'patient_document_id' => $document->id,
                    'user_id' => $actor->id,
                    'target_type' => $summary->getMorphClass(),
                    'target_id' => $summary->id,
                    'field_path' => 'sections',
                    'previous_value' => $previous,
                    'new_value' => $correctedSections,
                    'reason' => $reason,
                    'reviewed_at' => now(),
                ]);
            }

            return $document->fresh([
                'currentExtraction',
                'currentSummary',
                'currentTranslation',
                'currentExtractedText',
            ]);
        });
    }
}
