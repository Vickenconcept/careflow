<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\PatientEnquiry;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PatientEnquiry>
 */
class PatientEnquiryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'patient_id' => Patient::factory(),
            'department_id' => null,
            'submission_uuid' => (string) Str::uuid(),
            'preferred_language' => 'en',
            'reason_for_contact' => fake()->sentence(),
            'diagnosis' => fake()->optional()->sentence(),
            'symptoms_or_history' => fake()->optional()->paragraph(),
            'previous_procedures' => null,
            'current_medications' => null,
            'known_allergies' => null,
            'additional_comments' => null,
            'source_ip' => '127.0.0.1',
            'user_agent' => 'CareFlowTest/1.0',
            'submitted_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (PatientEnquiry $enquiry): void {
            if ($enquiry->patient_id && ! $enquiry->team_id) {
                $enquiry->team_id = Patient::query()->find($enquiry->patient_id)?->team_id;
            }
        });
    }
}
