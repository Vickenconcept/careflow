<?php

namespace Database\Factories;

use App\Enums\DoctorReviewStatus;
use App\Models\DoctorReview;
use App\Models\PatientCase;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DoctorReview>
 */
class DoctorReviewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'patient_case_id' => PatientCase::factory(),
            'reviewer_user_id' => User::factory(),
            'uuid' => (string) Str::uuid(),
            'status' => DoctorReviewStatus::InProgress,
            'findings' => null,
            'additional_information_request' => null,
            'outcome' => null,
            'started_at' => now(),
            'completed_at' => null,
            'is_demo' => false,
        ];
    }
}
