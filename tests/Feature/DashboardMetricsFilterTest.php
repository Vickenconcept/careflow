<?php

use App\Enums\CaseStatus;
use App\Enums\TeamRole;
use App\Models\Department;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\PatientEnquiry;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('dashboard metrics honor status and department filters', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;

    $department = Department::factory()->create([
        'team_id' => $team->id,
        'name' => 'Cardiology',
    ]);

    $patient = Patient::factory()->create([
        'team_id' => $team->id,
        'country_of_residence' => 'TR',
        'preferred_language' => 'en',
    ]);
    $enquiry = PatientEnquiry::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
    ]);

    PatientCase::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_enquiry_id' => $enquiry->id,
        'department_id' => $department->id,
        'status' => CaseStatus::AwaitingDoctorReview,
        'reference' => 'CF-2026-660001',
    ]);

    PatientCase::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_enquiry_id' => $enquiry->id,
        'department_id' => $department->id,
        'status' => CaseStatus::AwaitingCoordinatorReview,
        'reference' => 'CF-2026-660002',
    ]);

    $this->actingAs($owner)
        ->get(route('dashboard', [
            'current_team' => $team,
            'status' => CaseStatus::AwaitingDoctorReview->value,
            'department_id' => $department->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('filters.status', CaseStatus::AwaitingDoctorReview->value)
            ->where('filters.department_id', $department->id)
            ->where('metrics.open_cases', 1)
            ->where('metrics.awaiting_doctor_review', 1)
            ->where('metrics.awaiting_coordinator_review', 0)
            ->has('filterOptions.departments'));
});

test('dashboard filter options include coordinators and doctors for the organization', function () {
    $owner = User::factory()->create();
    $doctor = User::factory()->create();
    $team = $owner->currentTeam;

    $team->members()->attach($doctor, ['role' => TeamRole::Doctor->value]);

    $this->actingAs($owner)
        ->get(route('dashboard', ['current_team' => $team]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('filterOptions.doctors')
            ->has('filterOptions.coordinators')
            ->has('filterOptions.languages'));
});
