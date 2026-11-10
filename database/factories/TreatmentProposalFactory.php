<?php

namespace Database\Factories;

use App\Enums\TreatmentProposalStatus;
use App\Models\PatientCase;
use App\Models\Team;
use App\Models\TreatmentProposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TreatmentProposal>
 */
class TreatmentProposalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'patient_case_id' => PatientCase::factory(),
            'department_id' => null,
            'responsible_physician_id' => null,
            'clinical_recommendation_id' => null,
            'created_by_user_id' => User::factory(),
            'parent_proposal_id' => null,
            'uuid' => (string) Str::uuid(),
            'reference' => sprintf('TP-%d-%06d', now()->year, fake()->unique()->numberBetween(1, 999999)),
            'version' => 1,
            'status' => TreatmentProposalStatus::Draft,
            'proposed_procedure' => 'Demonstration outpatient evaluation',
            'estimated_cost' => 1500,
            'currency' => 'EUR',
            'expected_duration' => '2 days',
            'required_appointments' => 1,
            'additional_information' => null,
            'valid_until' => now()->addDays(30)->toDateString(),
            'is_current' => true,
            'is_demo' => false,
        ];
    }
}
