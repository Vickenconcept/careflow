<?php

namespace Database\Factories;

use App\Enums\CaseActivityType;
use App\Models\CaseActivity;
use App\Models\PatientCase;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CaseActivity>
 */
class CaseActivityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'patient_case_id' => PatientCase::factory(),
            'actor_user_id' => null,
            'activity_type' => CaseActivityType::NoteAdded,
            'summary' => fake()->sentence(),
            'properties' => null,
            'is_patient_visible' => false,
            'occurred_at' => now(),
        ];
    }
}
