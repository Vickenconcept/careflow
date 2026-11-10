<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\WorkflowExecutionStatus;
use App\Enums\WorkflowKey;
use Database\Factories\WorkflowExecutionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'team_id',
    'patient_case_id',
    'workflow_definition_id',
    'uuid',
    'workflow_key',
    'idempotency_key',
    'status',
    'context',
    'attempt',
    'started_at',
    'finished_at',
    'error_message',
])]
class WorkflowExecution extends Model
{
    /** @use HasFactory<WorkflowExecutionFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * @return BelongsTo<PatientCase, $this>
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(PatientCase::class, 'patient_case_id');
    }

    /**
     * @return BelongsTo<WorkflowDefinition, $this>
     */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id');
    }

    /**
     * @return HasMany<WorkflowExecutionStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowExecutionStep::class)->orderBy('sort_order')->orderBy('id');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'workflow_key' => WorkflowKey::class,
            'status' => WorkflowExecutionStatus::class,
            'context' => 'array',
            'attempt' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
