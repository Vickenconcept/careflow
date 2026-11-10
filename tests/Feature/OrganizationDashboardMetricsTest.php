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
use Inertia\Testing\AssertableInertia as Assert;

test('dashboard exposes live case metrics for authorized organization members', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;

    $patient = Patient::factory()->create(['team_id' => $team->id]);
    $enquiry = PatientEnquiry::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
    ]);

    $case = PatientCase::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_enquiry_id' => $enquiry->id,
        'status' => CaseStatus::AwaitingCoordinatorReview,
        'reference' => 'CF-2026-900001',
        'is_demo' => true,
    ]);

    PatientFollowUp::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'created_by_user_id' => $owner->id,
        'assigned_user_id' => $owner->id,
        'follow_up_type' => FollowUpType::Administrative,
        'status' => FollowUpStatus::Scheduled,
        'due_at' => now()->subDay(),
        'is_demo' => true,
    ]);

    $this->actingAs($owner)
        ->get(route('dashboard', ['current_team' => $team]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('metrics.available', true)
            ->where('metrics.open_cases', 1)
            ->where('metrics.awaiting_coordinator_review', 1)
            ->where('metrics.demo_cases', 1)
            ->where('metrics.executive.overdue_follow_ups', 1)
            ->where('metrics.executive.total_enquiries', 1)
            ->where('metrics.operational.outstanding_tasks', 0)
            ->has('metrics.definitions')
            ->has('metrics.recent_cases', 1));
});

test('management viewers receive aggregate metrics without recent case lists', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($viewer, ['role' => TeamRole::ManagementViewer->value]);
    $viewer->switchTeam($team);

    $patient = Patient::factory()->create(['team_id' => $team->id]);
    $enquiry = PatientEnquiry::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
    ]);

    PatientCase::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_enquiry_id' => $enquiry->id,
        'status' => CaseStatus::AwaitingDoctorReview,
        'reference' => 'CF-2026-900002',
    ]);

    $this->actingAs($viewer)
        ->get(route('dashboard', ['current_team' => $team]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('metrics.available', true)
            ->where('metrics.executive.active_cases', 1)
            ->where('metrics.executive.awaiting_doctor_review', 1)
            ->has('metrics.recent_cases', 0));
});
