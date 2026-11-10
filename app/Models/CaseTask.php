<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\CaseTaskPriority;
use App\Enums\CaseTaskStatus;
use Database\Factories\CaseTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'team_id',
    'patient_case_id',
    'assigned_user_id',
    'created_by_user_id',
    'completed_by_user_id',
    'uuid',
    'title',
    'description',
    'status',
    'priority',
    'due_at',
    'completed_at',
    'is_demo',
])]
class CaseTask extends Model
{
    /** @use HasFactory<CaseTaskFactory> */
    use BelongsToTeam, HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<PatientCase, $this>
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(PatientCase::class, 'patient_case_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    /**
     * @return HasMany<CaseTaskComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(CaseTaskComment::class)->orderBy('created_at');
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
            'status' => CaseTaskStatus::class,
            'priority' => CaseTaskPriority::class,
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }
}
