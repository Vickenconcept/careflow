<?php

namespace Database\Factories;

use App\Enums\ClinicalApprovalOutcome;
use App\Models\ClinicalApproval;
use App\Models\ClinicalRecommendation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClinicalApproval>
 */
class ClinicalApprovalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'clinical_recommendation_id' => ClinicalRecommendation::factory(),
            'approver_user_id' => User::factory(),
            'outcome' => ClinicalApprovalOutcome::Approved,
            'notes' => null,
            'decided_at' => now(),
        ];
    }
}
