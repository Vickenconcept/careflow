<?php

namespace Database\Factories;

use App\Enums\FollowUpStatus;
use App\Enums\FollowUpType;
use App\Models\PatientCase;
use App\Models\PatientFollowUp;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PatientFollowUp>
 */
class PatientFollowUpFactory extends Factory
{
    protected $model = PatientFollowUp::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'patient_case_id' => PatientCase::factory(),
            'assigned_user_id' => null,
            'created_by_user_id' => User::factory(),
            'completed_by_user_id' => null,
            'uuid' => (string) Str::uuid(),
            'follow_up_type' => FollowUpType::Administrative,
            'status' => FollowUpStatus::Scheduled,
            'title' => 'Post-treatment check-in',
            'description' => null,
            'due_at' => now()->addDays(7),
            'completed_at' => null,
            'outcome_notes' => null,
            'is_demo' => false,
        ];
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'status' => FollowUpStatus::Scheduled,
            'due_at' => now()->subDays(2),
        ]);
    }
}
