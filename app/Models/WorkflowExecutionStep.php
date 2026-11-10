<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\WorkflowStepStatus;
use Database\Factories\WorkflowExecutionStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'team_id',
    'workflow_execution_id',
    'step_key',
    'sort_order',
    'action',
    'status',
    'config',
    'result',
    'error_message',
    'started_at',
    'finished_at',
])]
class WorkflowExecutionStep extends Model
{
    /** @use HasFactory<WorkflowExecutionStepFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * @return BelongsTo<WorkflowExecution, $this>
     */
    public function execution(): BelongsTo
    {
        return $this->belongsTo(WorkflowExecution::class, 'workflow_execution_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WorkflowStepStatus::class,
            'config' => 'array',
            'result' => 'array',
            'sort_order' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
