<?php

namespace Database\Factories;

use App\Enums\CaseTaskPriority;
use App\Enums\CaseTaskStatus;
use App\Models\CaseTask;
use App\Models\PatientCase;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CaseTask>
 */
class CaseTaskFactory extends Factory
{
    protected $model = CaseTask::class;

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
            'title' => 'Follow up with patient',
            'description' => null,
            'status' => CaseTaskStatus::Open,
            'priority' => CaseTaskPriority::Normal,
            'due_at' => now()->addDays(2),
            'completed_at' => null,
            'is_demo' => false,
        ];
    }
}
