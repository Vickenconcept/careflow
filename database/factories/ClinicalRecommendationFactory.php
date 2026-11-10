<?php

namespace Database\Factories;

use App\Enums\ClinicalRecommendationStatus;
use App\Models\ClinicalRecommendation;
use App\Models\PatientCase;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ClinicalRecommendation>
 */
class ClinicalRecommendationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'patient_case_id' => PatientCase::factory(),
            'doctor_review_id' => null,
            'author_user_id' => User::factory(),
            'parent_recommendation_id' => null,
            'uuid' => (string) Str::uuid(),
            'version' => 1,
            'status' => ClinicalRecommendationStatus::Draft,
            'recommendation_text' => 'Human-authored clinical recommendation draft.',
            'supporting_document_uuids' => [],
            'is_ai_generated' => false,
            'is_current' => true,
        ];
    }
}
