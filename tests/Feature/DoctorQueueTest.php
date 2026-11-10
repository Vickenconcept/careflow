<?php

use App\Enums\CaseStatus;
use App\Enums\TeamRole;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\PatientEnquiry;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('doctors see assigned clinical backlog cases in the doctor queue', function () {
    $owner = User::factory()->create();
    $doctor = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($doctor, ['role' => TeamRole::Doctor->value]);
    $doctor->switchTeam($team);

    $patient = Patient::factory()->create(['team_id' => $team->id]);
    $enquiry = PatientEnquiry::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
    ]);

    $assigned = PatientCase::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_enquiry_id' => $enquiry->id,
        'assigned_doctor_id' => $doctor->id,
        'status' => CaseStatus::AwaitingDoctorReview,
        'reference' => 'CF-2026-880001',
    ]);

    $otherDoctor = User::factory()->create();
    $team->members()->attach($otherDoctor, ['role' => TeamRole::Doctor->value]);

    PatientCase::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_enquiry_id' => $enquiry->id,
        'assigned_doctor_id' => $otherDoctor->id,
        'status' => CaseStatus::AwaitingDoctorReview,
        'reference' => 'CF-2026-880002',
    ]);

    $this->actingAs($doctor)
        ->get(route('organizations.doctor-queue', ['current_team' => $team]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('organizations/doctor-queue')
            ->has('queue', 1)
            ->where('queue.0.reference', $assigned->reference)
            ->where('queue.0.assigned_to_me', true));
});

test('coordinators can open the doctor queue for organization clinical backlog', function () {
    $owner = User::factory()->create();
    $coordinator = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($coordinator, ['role' => TeamRole::Coordinator->value]);
    $coordinator->switchTeam($team);

    $patient = Patient::factory()->create(['team_id' => $team->id]);
    $enquiry = PatientEnquiry::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
    ]);

    PatientCase::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_enquiry_id' => $enquiry->id,
        'status' => CaseStatus::TreatmentProposalPreparation,
        'reference' => 'CF-2026-880003',
    ]);

    $this->actingAs($coordinator)
        ->get(route('organizations.doctor-queue', ['current_team' => $team]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('organizations/doctor-queue')
            ->has('queue', 1));
});
