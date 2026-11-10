<?php

namespace Database\Factories;

use App\Enums\CasePriority;
use App\Enums\CaseStatus;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\PatientEnquiry;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientCase>
 */
class PatientCaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'patient_id' => Patient::factory(),
            'patient_enquiry_id' => PatientEnquiry::factory(),
            'department_id' => null,
            'reference' => sprintf('CF-%d-%06d', now()->year, fake()->unique()->numberBetween(1, 999999)),
            'status' => CaseStatus::NewEnquiry,
            'priority' => CasePriority::Normal,
            'assigned_coordinator_id' => null,
            'assigned_doctor_id' => null,
            'last_activity_at' => now(),
            'is_demo' => false,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (PatientCase $case): void {
            if ($case->patient_id) {
                $patient = Patient::query()->find($case->patient_id);

                if ($patient) {
                    $case->team_id = $patient->team_id;
                }
            }
        });
    }
}
