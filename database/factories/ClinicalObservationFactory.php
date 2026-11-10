<?php

namespace Database\Factories;

use App\Models\ClinicalObservation;
use App\Models\PatientCase;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClinicalObservation>
 */
class ClinicalObservationFactory extends Factory
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
            'body' => 'Human clinical observation for demonstration.',
            'visibility' => 'internal',
            'observed_at' => now(),
        ];
    }
}
