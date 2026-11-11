<?php

namespace App\Actions\Patients;

use App\Models\DocumentReviewEvent;
use App\Models\PatientDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SignOffDocumentTranslation
{
    /**
     * Record that a person checked the current AI translation and accepts it for internal use.
     */
    public function handle(PatientDocument $document, User $actor, ?string $note = null): PatientDocument
    {
        return DB::transaction(function () use ($document, $actor, $note) {
            $translation = $document->currentTranslation()->lockForUpdate()->first();

            if ($translation === null) {
                throw new InvalidArgumentException('There is no translation to sign off yet.');
            }

            if ($translation->reviewed_by !== null) {
                throw new InvalidArgumentException('This translation has already been signed off.');
            }

            $translation->update([
                'reviewed_by' => $actor->id,
                'review_outcome' => 'approved',
            ]);

            DocumentReviewEvent::query()->create([
                'team_id' => $document->team_id,
                'patient_document_id' => $document->id,
                'user_id' => $actor->id,
                'target_type' => $translation->getMorphClass(),
                'target_id' => $translation->id,
                'field_path' => 'translation_signed_off',
                'previous_value' => null,
                'new_value' => 'approved',
                'reason' => $note,
                'reviewed_at' => now(),
            ]);

            return $document->fresh(['currentTranslation']);
        });
    }
}
