<?php

use App\Enums\CaseStatus;
use App\Enums\FollowUpStatus;
use App\Enums\FollowUpType;
use App\Enums\TeamRole;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\PatientEnquiry;
use App\Models\PatientFollowUp;
use App\Models\Team;
use App\Models\User;

test('coordinators can schedule and complete patient follow-ups', function () {
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
        'status' => CaseStatus::FollowUp,
        'reference' => 'CF-2026-940101',
    ]);

    $this->actingAs($coordinator)
        ->post(route('organizations.follow-ups.store', [
            'current_team' => $team,
            'case' => $case,
        ]), [
            'title' => 'Day-14 recovery check',
            'description' => 'Administrative wellness check-in',
            'follow_up_type' => FollowUpType::ClinicalCheckIn->value,
            'due_at' => now()->addDays(2)->toDateTimeString(),
            'assigned_user_id' => $coordinator->id,
        ])
        ->assertRedirect();

    $followUp = PatientFollowUp::query()->where('patient_case_id', $case->id)->firstOrFail();

    expect($followUp->status)->toBe(FollowUpStatus::Scheduled)
        ->and($followUp->title)->toBe('Day-14 recovery check')
        ->and($followUp->follow_up_type)->toBe(FollowUpType::ClinicalCheckIn);

    $this->actingAs($coordinator)
        ->post(route('organizations.follow-ups.complete', [
            'current_team' => $team,
            'case' => $case,
            'followUp' => $followUp,
        ]), [
            'outcome_notes' => 'Patient confirmed recovery progress',
            'cancel' => false,
        ])
        ->assertRedirect();

    expect($followUp->fresh()->status)->toBe(FollowUpStatus::Completed)
        ->and($followUp->fresh()->completed_at)->not->toBeNull()
        ->and($followUp->fresh()->outcome_notes)->toBe('Patient confirmed recovery progress');
});

test('management viewers cannot manage follow-ups', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $team = Team::factory()->create();
    $team->ensureOrganizationSetting();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($viewer, ['role' => TeamRole::ManagementViewer->value]);
    $viewer->switchTeam($team);

    $patient = Patient::factory()->create(['team_id' => $team->id]);
    $enquiry = PatientEnquiry::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
    ]);
    $case = PatientCase::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_enquiry_id' => $enquiry->id,
        'status' => CaseStatus::FollowUp,
        'reference' => 'CF-2026-940102',
    ]);

    $this->actingAs($viewer)
        ->post(route('organizations.follow-ups.store', [
            'current_team' => $team,
            'case' => $case,
        ]), [
            'title' => 'Should not create',
            'follow_up_type' => FollowUpType::Administrative->value,
            'due_at' => now()->addDay()->toDateTimeString(),
        ])
        ->assertForbidden();
});
