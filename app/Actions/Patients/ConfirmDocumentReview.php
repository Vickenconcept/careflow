<?php

namespace App\Actions\Patients;

use App\Models\DocumentReviewEvent;
use App\Models\PatientDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ConfirmDocumentReview
{
    /**
     * Record that a human reviewed the AI draft and found no corrections necessary.
     */
    public function handle(PatientDocument $document, User $actor, ?string $reason = null): PatientDocument
    {
        return DB::transaction(function () use ($document, $actor, $reason) {
            $extraction = $document->currentExtraction()->lockForUpdate()->first();
            $summary = $document->currentSummary()->lockForUpdate()->first();

            if ($extraction === null && $summary === null) {
                throw new InvalidArgumentException('There is no AI output to review yet.');
            }

            foreach ([$extraction, $summary] as $target) {
                if ($target === null || $target->review_status !== 'unreviewed') {
                    continue;
                }

                $target->update(['review_status' => 'reviewed']);

                DocumentReviewEvent::query()->create([
                    'team_id' => $document->team_id,
                    'patient_document_id' => $document->id,
                    'user_id' => $actor->id,
                    'target_type' => $target->getMorphClass(),
                    'target_id' => $target->id,
                    'field_path' => 'review_confirmed',
                    'previous_value' => 'unreviewed',
                    'new_value' => 'reviewed',
                    'reason' => $reason,
                    'reviewed_at' => now(),
                ]);
            }

            return $document->fresh(['currentExtraction', 'currentSummary']);
        });
    }
}
