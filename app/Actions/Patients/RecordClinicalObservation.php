<?php

namespace App\Actions\Patients;

use App\Enums\CaseActivityType;
use App\Models\ClinicalObservation;
use App\Models\PatientCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RecordClinicalObservation
{
    public function __construct(
        private EnsureOpenDoctorReview $ensureOpenDoctorReview,
        private RecordCaseActivity $recordActivity,
    ) {}

    public function handle(
        PatientCase $case,
        User $author,
        string $body,
        string $visibility = 'internal',
    ): ClinicalObservation {
        return DB::transaction(function () use ($case, $author, $body, $visibility) {
            $review = $this->ensureOpenDoctorReview->handle($case, $author);

            $observation = ClinicalObservation::query()->create([
                'team_id' => $case->team_id,
                'patient_case_id' => $case->id,
                'doctor_review_id' => $review->id,
                'author_user_id' => $author->id,
                'body' => $body,
                'visibility' => $visibility,
                'observed_at' => now(),
            ]);

            $case->forceFill(['last_activity_at' => now()])->save();

            $this->recordActivity->handle(
                case: $case,
                type: CaseActivityType::ClinicalObservationAdded,
                summary: 'Clinical observation recorded',
                actor: $author,
                properties: [
                    'observation_id' => $observation->id,
                    'doctor_review_uuid' => $review->uuid,
                    'visibility' => $visibility,
                ],
            );

            return $observation;
        });
    }
}
