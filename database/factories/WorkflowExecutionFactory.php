<?php

namespace Database\Factories;

use App\Enums\WorkflowExecutionStatus;
use App\Enums\WorkflowKey;
use App\Models\PatientCase;
use App\Models\Team;
use App\Models\WorkflowExecution;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WorkflowExecution>
 */
class WorkflowExecutionFactory extends Factory
{
    protected $model = WorkflowExecution::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'patient_case_id' => PatientCase::factory(),
            'workflow_definition_id' => null,
            'uuid' => (string) Str::uuid(),
            'workflow_key' => WorkflowKey::NewPatientEnquiry,
            'idempotency_key' => 'test:'.Str::uuid(),
            'status' => WorkflowExecutionStatus::Completed,
            'context' => [],
            'attempt' => 1,
            'started_at' => now(),
            'finished_at' => now(),
            'error_message' => null,
        ];
    }
}
