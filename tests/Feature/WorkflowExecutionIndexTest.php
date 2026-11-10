<?php

use App\Enums\WorkflowExecutionStatus;
use App\Enums\WorkflowKey;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\PatientEnquiry;
use App\Models\User;
use App\Models\WorkflowExecution;
use Inertia\Testing\AssertableInertia as Assert;

test('organization members can view recent workflow executions', function () {
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
        'reference' => 'CF-2026-770001',
    ]);

    WorkflowExecution::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'workflow_key' => WorkflowKey::NewPatientEnquiry,
        'status' => WorkflowExecutionStatus::Completed,
        'idempotency_key' => 'new-enquiry:'.$case->id,
    ]);

    $this->actingAs($owner)
        ->get(route('organizations.workflows.index', ['current_team' => $team]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('organizations/workflows/index')
            ->has('executions', 1)
            ->where('executions.0.case_reference', $case->reference)
            ->where('executions.0.status', WorkflowExecutionStatus::Completed->value));
});
