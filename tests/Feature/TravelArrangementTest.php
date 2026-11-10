<?php

use App\Enums\CaseStatus;
use App\Enums\TeamRole;
use App\Enums\TravelArrangementStatus;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\PatientEnquiry;
use App\Models\Team;
use App\Models\TravelArrangement;
use App\Models\User;

test('coordinators can create and update travel arrangements without passport fields', function () {
    $owner = User::factory()->create();
    $coordinator = User::factory()->create();
    $team = Team::factory()->create();
    $team->ensureOrganizationSetting();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($coordinator, ['role' => TeamRole::Coordinator->value]);
    $coordinator->switchTeam($team);

    $patient = Patient::factory()->create(['team_id' => $team->id]);
    $enquiry = PatientEnquiry::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
    ]);
    $case = PatientCase::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_enquiry_id' => $enquiry->id,
        'assigned_coordinator_id' => $coordinator->id,
        'status' => CaseStatus::TravelCoordination,
        'reference' => 'CF-2026-930001',
    ]);

    $this->actingAs($coordinator)
        ->put(route('organizations.travel.upsert', [
            'current_team' => $team,
            'case' => $case,
        ]), [
            'status' => TravelArrangementStatus::InProgress->value,
            'expected_arrival_date' => now()->addDays(8)->toDateString(),
            'expected_departure_date' => now()->addDays(15)->toDateString(),
            'flight_information' => 'TK123 ARR 10:00',
            'hotel_details' => 'Near campus hotel',
            'visa_assistance_status' => 'pending',
            'airport_pickup_required' => true,
            'airport_pickup_details' => 'Pickup at Larnaca',
            'assigned_coordinator_id' => $coordinator->id,
        ])
        ->assertRedirect();

    $travel = TravelArrangement::query()->where('patient_case_id', $case->id)->firstOrFail();

    expect($travel->status)->toBe(TravelArrangementStatus::InProgress)
        ->and($travel->flight_information)->toBe('TK123 ARR 10:00')
        ->and($travel->checklist)->toBeArray()
        ->and($travel->checklist)->not->toBeEmpty();
});
