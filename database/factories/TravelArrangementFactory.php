<?php

namespace Database\Factories;

use App\Enums\TravelArrangementStatus;
use App\Enums\VisaAssistanceStatus;
use App\Models\PatientCase;
use App\Models\Team;
use App\Models\TravelArrangement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TravelArrangement>
 */
class TravelArrangementFactory extends Factory
{
    protected $model = TravelArrangement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'patient_case_id' => PatientCase::factory(),
            'assigned_coordinator_id' => null,
            'updated_by_user_id' => User::factory(),
            'uuid' => (string) Str::uuid(),
            'status' => TravelArrangementStatus::Draft,
            'expected_arrival_date' => now()->addDays(10)->toDateString(),
            'expected_departure_date' => now()->addDays(17)->toDateString(),
            'flight_information' => null,
            'airport_pickup_required' => false,
            'airport_pickup_details' => null,
            'transfer_arrangements' => null,
            'accommodation' => null,
            'hotel_details' => null,
            'visa_assistance_status' => VisaAssistanceStatus::NotRequired,
            'special_administrative_requirements' => null,
            'checklist' => [
                ['key' => 'arrival_confirmed', 'label' => 'Arrival date confirmed', 'done' => false],
                ['key' => 'transfer_booked', 'label' => 'Transfer arranged', 'done' => false],
                ['key' => 'accommodation_booked', 'label' => 'Accommodation arranged', 'done' => false],
            ],
            'notes' => null,
            'is_demo' => false,
        ];
    }
}
