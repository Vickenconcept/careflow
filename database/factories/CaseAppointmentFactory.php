<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Models\CaseAppointment;
use App\Models\PatientCase;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CaseAppointment>
 */
class CaseAppointmentFactory extends Factory
{
    protected $model = CaseAppointment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->addDays(3)->setTime(10, 0);

        return [
            'team_id' => Team::factory(),
            'patient_case_id' => PatientCase::factory(),
            'department_id' => null,
            'doctor_id' => null,
            'created_by_user_id' => User::factory(),
            'confirmed_by_user_id' => null,
            'uuid' => (string) Str::uuid(),
            'status' => AppointmentStatus::Proposed,
            'appointment_type' => AppointmentType::Consultation,
            'proposed_starts_at' => $startsAt,
            'proposed_ends_at' => $startsAt->copy()->addHour(),
            'confirmed_starts_at' => null,
            'confirmed_ends_at' => null,
            'location' => 'International Patient Clinic',
            'meeting_instructions' => null,
            'external_his_reference' => null,
            'confirmation_source' => null,
            'hospital_slot_verified' => false,
            'confirmed_at' => null,
            'notes' => null,
            'is_demo' => false,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(function (array $attributes) {
            $startsAt = $attributes['proposed_starts_at'] ?? now()->addDays(3)->setTime(10, 0);
            $endsAt = $attributes['proposed_ends_at'] ?? now()->addDays(3)->setTime(11, 0);

            return [
                'status' => AppointmentStatus::Confirmed,
                'confirmed_starts_at' => $startsAt,
                'confirmed_ends_at' => $endsAt,
                'confirmation_source' => 'manual_hospital_verified',
                'hospital_slot_verified' => true,
                'confirmed_at' => now(),
            ];
        });
    }
}
