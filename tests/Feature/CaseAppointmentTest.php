<?php

use App\Enums\AppointmentStatus;
use App\Enums\CaseStatus;
use App\Enums\TeamRole;
use App\Enums\TreatmentProposalStatus;
use App\Models\CaseAppointment;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\PatientEnquiry;
use App\Models\Team;
use App\Models\TreatmentProposal;
use App\Models\User;

function appointmentCase(Team $team, array $overrides = []): PatientCase
{
    $patient = Patient::factory()->create(['team_id' => $team->id]);
    $enquiry = PatientEnquiry::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
    ]);

    return PatientCase::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_enquiry_id' => $enquiry->id,
        'status' => CaseStatus::AppointmentPlanning,
        'reference' => 'CF-2026-'.fake()->unique()->numerify('92####'),
        ...$overrides,
    ]);
}

test('coordinators can propose appointments and request confirmation', function () {
    $owner = User::factory()->create();
    $coordinator = User::factory()->create();
    $doctor = User::factory()->create();
    $team = Team::factory()->create();
    $team->ensureOrganizationSetting();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($coordinator, ['role' => TeamRole::Coordinator->value]);
    $team->members()->attach($doctor, ['role' => TeamRole::Doctor->value]);
    $coordinator->switchTeam($team);

    $case = appointmentCase($team, [
        'assigned_coordinator_id' => $coordinator->id,
        'assigned_doctor_id' => $doctor->id,
        'reference' => 'CF-2026-920001',
    ]);

    $this->actingAs($coordinator)
        ->post(route('organizations.appointments.store', [
            'current_team' => $team,
            'case' => $case,
        ]), [
            'appointment_type' => 'consultation',
            'proposed_starts_at' => now()->addDays(5)->setTime(9, 30)->toDateTimeString(),
            'location' => 'Clinic A',
            'doctor_id' => $doctor->id,
        ])
        ->assertRedirect();

    $appointment = CaseAppointment::query()->where('patient_case_id', $case->id)->firstOrFail();

    expect($appointment->status)->toBe(AppointmentStatus::Proposed)
        ->and($appointment->hospital_slot_verified)->toBeFalse();

    $this->actingAs($coordinator)
        ->post(route('organizations.appointments.transition', [
            'current_team' => $team,
            'case' => $case,
            'appointment' => $appointment,
        ]), [
            'action' => 'request_confirmation',
        ])
        ->assertRedirect();

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::AwaitingConfirmation);
});

test('appointments cannot be confirmed without hospital verification evidence', function () {
    $owner = User::factory()->create();
    $coordinator = User::factory()->create();
    $team = Team::factory()->create();
    $team->ensureOrganizationSetting();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($coordinator, ['role' => TeamRole::Coordinator->value]);
    $coordinator->switchTeam($team);

    $case = appointmentCase($team, [
        'assigned_coordinator_id' => $coordinator->id,
        'reference' => 'CF-2026-920002',
    ]);

    $appointment = CaseAppointment::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'created_by_user_id' => $coordinator->id,
        'status' => AppointmentStatus::AwaitingConfirmation,
    ]);

    $this->actingAs($coordinator)
        ->from(route('organizations.cases.show', [
            'current_team' => $team,
            'case' => $case,
        ]))
        ->post(route('organizations.appointments.transition', [
            'current_team' => $team,
            'case' => $case,
            'appointment' => $appointment,
        ]), [
            'action' => 'confirm',
            'hospital_slot_verified' => false,
            'confirmation_source' => 'manual_hospital_verified',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('appointment');

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::AwaitingConfirmation);
});

test('hospital-verified confirmation unlocks treatment scheduled case status', function () {
    $owner = User::factory()->create();
    $coordinator = User::factory()->create();
    $team = Team::factory()->create();
    $team->ensureOrganizationSetting();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($coordinator, ['role' => TeamRole::Coordinator->value]);
    $coordinator->switchTeam($team);

    $case = appointmentCase($team, [
        'assigned_coordinator_id' => $coordinator->id,
        'status' => CaseStatus::AppointmentPlanning,
        'reference' => 'CF-2026-920003',
    ]);

    TreatmentProposal::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'created_by_user_id' => $coordinator->id,
        'status' => TreatmentProposalStatus::PatientAccepted,
        'is_current' => true,
    ]);

    $appointment = CaseAppointment::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'created_by_user_id' => $coordinator->id,
        'status' => AppointmentStatus::AwaitingConfirmation,
        'proposed_starts_at' => now()->addDays(4)->setTime(11, 0),
    ]);

    $this->actingAs($coordinator)
        ->post(route('organizations.appointments.transition', [
            'current_team' => $team,
            'case' => $case,
            'appointment' => $appointment,
        ]), [
            'action' => 'confirm',
            'hospital_slot_verified' => true,
            'confirmation_source' => 'manual_hospital_verified',
            'confirmed_starts_at' => $appointment->proposed_starts_at->toDateTimeString(),
            'notes' => 'Verified with hospital desk',
        ])
        ->assertRedirect();

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Confirmed)
        ->and($appointment->fresh()->hospital_slot_verified)->toBeTrue();

    $this->actingAs($coordinator)
        ->patch(route('organizations.cases.status.update', [
            'current_team' => $team,
            'case' => $case,
        ]), [
            'status' => CaseStatus::TreatmentScheduled->value,
            'note' => 'Treatment slot locked with hospital',
        ])
        ->assertRedirect();

    expect($case->fresh()->status)->toBe(CaseStatus::TreatmentScheduled);
});
