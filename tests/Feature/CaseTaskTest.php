<?php

use App\Enums\CaseStatus;
use App\Enums\CaseTaskStatus;
use App\Enums\TeamRole;
use App\Models\CaseTask;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\PatientEnquiry;
use App\Models\Team;
use App\Models\User;

test('coordinators can create and complete case tasks', function () {
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
        'status' => CaseStatus::AppointmentPlanning,
        'reference' => 'CF-2026-930101',
    ]);

    $this->actingAs($coordinator)
        ->post(route('organizations.tasks.store', [
            'current_team' => $team,
            'case' => $case,
        ]), [
            'title' => 'Confirm hotel transfer',
            'priority' => 'high',
            'assigned_user_id' => $coordinator->id,
            'due_at' => now()->addDay()->toDateTimeString(),
        ])
        ->assertRedirect();

    $task = CaseTask::query()->where('patient_case_id', $case->id)->firstOrFail();

    expect($task->status)->toBe(CaseTaskStatus::Open)
        ->and($task->title)->toBe('Confirm hotel transfer');

    $this->actingAs($coordinator)
        ->post(route('organizations.tasks.transition', [
            'current_team' => $team,
            'case' => $case,
            'task' => $task,
        ]), [
            'status' => CaseTaskStatus::Completed->value,
            'comment' => 'Transfer confirmed with driver',
        ])
        ->assertRedirect();

    expect($task->fresh()->status)->toBe(CaseTaskStatus::Completed)
        ->and($task->fresh()->completed_at)->not->toBeNull()
        ->and($task->comments)->toHaveCount(1);
});
