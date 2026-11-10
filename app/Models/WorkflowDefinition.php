<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\WorkflowKey;
use Database\Factories\WorkflowDefinitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'team_id',
    'uuid',
    'key',
    'name',
    'trigger',
    'is_enabled',
    'steps',
    'description',
])]
class WorkflowDefinition extends Model
{
    /** @use HasFactory<WorkflowDefinitionFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * @return HasMany<WorkflowExecution, $this>
     */
    public function executions(): HasMany
    {
        return $this->hasMany(WorkflowExecution::class);
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
            'key' => WorkflowKey::class,
            'is_enabled' => 'boolean',
            'steps' => 'array',
        ];
    }
}
