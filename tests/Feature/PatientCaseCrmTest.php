<?php

use App\Enums\CaseActivityType;
use App\Enums\CaseStatus;
use App\Enums\TeamRole;
use App\Models\CaseActivity;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\PatientEnquiry;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function crmCase(Team $team, array $overrides = []): PatientCase
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
        'reference' => 'CF-2026-'.fake()->unique()->numerify('######'),
        ...$overrides,
    ]);
}

test('cases index supports search and assignment filters', function () {
    $owner = User::factory()->create();
    $coordinator = User::factory()->create();
    $team = $owner->currentTeam;

    $team->members()->attach($coordinator, ['role' => TeamRole::Coordinator->value]);

    $mine = crmCase($team, [
        'reference' => 'CF-2026-500001',
        'assigned_coordinator_id' => $coordinator->id,
    ]);
    crmCase($team, [
        'reference' => 'CF-2026-500002',
        'assigned_coordinator_id' => null,
    ]);

    $this->actingAs($coordinator)
        ->get(route('organizations.cases.index', [
            'current_team' => $team,
            'assignment' => 'mine',
            'search' => '500001',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('organizations/cases/index')
            ->has('cases.data', 1)
            ->where('cases.data.0.reference', $mine->reference)
            ->where('filters.assignment', 'mine'));
});

test('coordinators can assign cases and activity is recorded', function () {
    $owner = User::factory()->create();
    $coordinator = User::factory()->create();
    $doctor = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($coordinator, ['role' => TeamRole::Coordinator->value]);
    $team->members()->attach($doctor, ['role' => TeamRole::Doctor->value]);
    $coordinator->switchTeam($team);

    $case = crmCase($team, ['reference' => 'CF-2026-510001']);

    $this->actingAs($coordinator)
        ->patch(route('organizations.cases.assignment.update', [
            'current_team' => $team,
            'case' => $case,
        ]), [
            'coordinator_id' => $coordinator->id,
            'doctor_id' => $doctor->id,
        ])
        ->assertRedirect();

    expect($case->fresh()->assigned_coordinator_id)->toBe($coordinator->id)
        ->and($case->fresh()->assigned_doctor_id)->toBe($doctor->id);

    $this->assertDatabaseHas('case_activities', [
        'patient_case_id' => $case->id,
        'activity_type' => CaseActivityType::CoordinatorAssigned->value,
        'actor_user_id' => $coordinator->id,
    ]);

    $this->assertDatabaseHas('case_activities', [
        'patient_case_id' => $case->id,
        'activity_type' => CaseActivityType::DoctorAssigned->value,
    ]);
});

test('valid status transitions are applied and illegal ones are rejected', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $case = crmCase($team, [
        'reference' => 'CF-2026-520001',
        'status' => CaseStatus::NewEnquiry,
    ]);

    $this->actingAs($owner)
        ->patch(route('organizations.cases.status.update', [
            'current_team' => $team,
            'case' => $case,
        ]), [
            'status' => CaseStatus::AwaitingDocuments->value,
            'note' => 'Waiting on labs',
        ])
        ->assertRedirect();

    expect($case->fresh()->status)->toBe(CaseStatus::AwaitingDocuments);

    expect(CaseActivity::query()
        ->where('patient_case_id', $case->id)
        ->where('activity_type', CaseActivityType::StatusChanged)
        ->exists())->toBeTrue();

    $this->actingAs($owner)
        ->from(route('organizations.cases.show', [$team, $case]))
        ->patch(route('organizations.cases.status.update', [
            'current_team' => $team,
            'case' => $case->fresh(),
        ]), [
            'status' => CaseStatus::TreatmentScheduled->value,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('status');

    expect($case->fresh()->status)->toBe(CaseStatus::AwaitingDocuments);
});

test('doctors cannot assign cases', function () {
    $owner = User::factory()->create();
    $doctor = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($doctor, ['role' => TeamRole::Doctor->value]);
    $doctor->switchTeam($team);

    $case = crmCase($team, [
        'reference' => 'CF-2026-530001',
        'assigned_doctor_id' => $doctor->id,
    ]);

    $this->actingAs($doctor)
        ->patch(route('organizations.cases.assignment.update', [
            'current_team' => $team,
            'case' => $case,
        ]), [
            'coordinator_id' => $owner->id,
        ])
        ->assertForbidden();
});

test('case workspace includes timeline activities', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $case = crmCase($team, ['reference' => 'CF-2026-540001']);

    CaseActivity::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'actor_user_id' => $owner->id,
        'activity_type' => CaseActivityType::NoteAdded,
        'summary' => 'Coordinator reviewed intake',
    ]);

    $this->actingAs($owner)
        ->get(route('organizations.cases.show', [
            'current_team' => $team,
            'case' => $case,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('organizations/cases/show')
            ->has('case.activities', 1)
            ->where('case.activities.0.summary', 'Coordinator reviewed intake')
            ->has('availableStatusTransitions')
            ->where('permissions.canAssignCases', true));
});
