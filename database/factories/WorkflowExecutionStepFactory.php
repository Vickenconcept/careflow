<?php

namespace Database\Factories;

use App\Enums\WorkflowStepStatus;
use App\Models\Team;
use App\Models\WorkflowExecution;
use App\Models\WorkflowExecutionStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowExecutionStep>
 */
class WorkflowExecutionStepFactory extends Factory
{
    protected $model = WorkflowExecutionStep::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'workflow_execution_id' => WorkflowExecution::factory(),
            'step_key' => 'notify_coordinator',
            'sort_order' => 1,
            'action' => 'notify_staff',
            'status' => WorkflowStepStatus::Completed,
            'config' => [],
            'result' => [],
            'error_message' => null,
            'started_at' => now(),
            'finished_at' => now(),
        ];
    }
}
