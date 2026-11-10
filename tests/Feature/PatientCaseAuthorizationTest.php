<?php

use App\Enums\CaseStatus;
use App\Enums\TeamRole;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\PatientEnquiry;
use App\Models\Team;
use App\Models\User;

function makeCaseForTeam(Team $team, array $overrides = []): PatientCase
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
        'status' => CaseStatus::NewEnquiry,
        ...$overrides,
    ]);
}

test('doctors can only view cases assigned to them', function () {
    $owner = User::factory()->create();
    $doctor = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($doctor, ['role' => TeamRole::Doctor->value]);
    $doctor->switchTeam($team);

    $assigned = makeCaseForTeam($team, [
        'reference' => 'CF-2026-100001',
        'assigned_doctor_id' => $doctor->id,
    ]);
    $unassigned = makeCaseForTeam($team, [
        'reference' => 'CF-2026-100002',
    ]);

    $this->actingAs($doctor)
        ->get(route('organizations.cases.show', [
            'current_team' => $team,
            'case' => $assigned,
        ]))
        ->assertOk();

    $this->actingAs($doctor)
        ->get(route('organizations.cases.show', [
            'current_team' => $team,
            'case' => $unassigned,
        ]))
        ->assertForbidden();
});

test('cross-tenant case access is denied even with a valid reference', function () {
    $user = User::factory()->create();
    $ownTeam = $user->currentTeam;
    $otherTeam = Team::factory()->create();

    $foreignCase = makeCaseForTeam($otherTeam, [
        'reference' => 'CF-2026-200001',
    ]);

    $this->actingAs($user)
        ->get(route('organizations.cases.show', [
            'current_team' => $ownTeam,
            'case' => $foreignCase,
        ]))
        ->assertNotFound();
});

test('management viewers cannot open case detail by default', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($viewer, ['role' => TeamRole::ManagementViewer->value]);
    $viewer->switchTeam($team);

    $case = makeCaseForTeam($team, [
        'reference' => 'CF-2026-300001',
    ]);

    $this->actingAs($viewer)
        ->get(route('organizations.cases.show', [
            'current_team' => $team,
            'case' => $case,
        ]))
        ->assertForbidden();
});
