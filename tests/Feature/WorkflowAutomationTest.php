<?php

use App\Domains\Workflows\WorkflowRunner;
use App\Enums\CaseStatus;
use App\Enums\CaseTaskStatus;
use App\Enums\CommunicationStatus;
use App\Enums\TeamRole;
use App\Enums\TreatmentProposalPatientResponse;
use App\Enums\TreatmentProposalStatus;
use App\Enums\WorkflowExecutionStatus;
use App\Enums\WorkflowKey;
use App\Models\CaseCommunication;
use App\Models\CaseTask;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\PatientEnquiry;
use App\Models\Team;
use App\Models\TreatmentProposal;
use App\Models\User;
use App\Models\WorkflowExecution;

test('new enquiry workflow creates coordinator task and approval draft idempotently', function () {
    $owner = User::factory()->create();
    $coordinator = User::factory()->create();
    $team = Team::factory()->create();
    $team->ensureOrganizationSetting();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($coordinator, ['role' => TeamRole::Coordinator->value]);

    $patient = Patient::factory()->create([
        'team_id' => $team->id,
        'first_name' => 'Ada',
        'last_name' => 'Patient',
    ]);
    $enquiry = PatientEnquiry::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
    ]);
    $case = PatientCase::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_enquiry_id' => $enquiry->id,
        'assigned_coordinator_id' => $coordinator->id,
        'status' => CaseStatus::NewEnquiry,
        'reference' => 'CF-2026-940001',
    ]);

    $runner = app(WorkflowRunner::class);

    $first = $runner->run(
        case: $case,
        workflowKey: WorkflowKey::NewPatientEnquiry,
        idempotencyKey: 'new_enquiry:'.$case->id,
        actor: $coordinator,
    );

    $second = $runner->run(
        case: $case,
        workflowKey: WorkflowKey::NewPatientEnquiry,
        idempotencyKey: 'new_enquiry:'.$case->id,
        actor: $coordinator,
    );

    expect($first->status)->toBe(WorkflowExecutionStatus::Completed)
        ->and($second->id)->toBe($first->id)
        ->and(WorkflowExecution::query()->where('patient_case_id', $case->id)->count())->toBe(1)
        ->and(CaseTask::query()->where('patient_case_id', $case->id)->count())->toBe(1)
        ->and(CaseCommunication::query()->where('patient_case_id', $case->id)->count())->toBe(1);

    $communication = CaseCommunication::query()->where('patient_case_id', $case->id)->firstOrFail();

    expect($communication->status)->toBe(CommunicationStatus::AwaitingApproval)
        ->and($communication->body)->toContain('CF-2026-940001');
});

test('accepted proposal workflow creates appointment and travel tasks', function () {
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
        'status' => CaseStatus::ProposalSent,
        'reference' => 'CF-2026-940002',
    ]);

    $proposal = TreatmentProposal::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'created_by_user_id' => $coordinator->id,
        'status' => TreatmentProposalStatus::SentToPatient,
        'is_current' => true,
    ]);

    $this->actingAs($coordinator)
        ->post(route('organizations.proposals.transition', [
            'current_team' => $team,
            'case' => $case,
            'proposal' => $proposal,
        ]), [
            'action' => 'record_patient_response',
            'patient_response' => TreatmentProposalPatientResponse::Accepted->value,
        ])
        ->assertRedirect();

    $titles = CaseTask::query()
        ->where('patient_case_id', $case->id)
        ->pluck('title')
        ->all();

    expect($titles)->toContain('Plan appointment with hospital scheduling')
        ->and($titles)->toContain('Start travel coordination')
        ->and(CaseTask::query()->where('patient_case_id', $case->id)->where('status', CaseTaskStatus::Open->value)->count())->toBe(2)
        ->and(WorkflowExecution::query()->where('workflow_key', WorkflowKey::TreatmentCoordination->value)->exists())->toBeTrue();
});
