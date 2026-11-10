<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\FollowUpStatus;
use App\Enums\FollowUpType;
use Database\Factories\PatientFollowUpFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'team_id',
    'patient_case_id',
    'assigned_user_id',
    'created_by_user_id',
    'completed_by_user_id',
    'uuid',
    'follow_up_type',
    'status',
    'title',
    'description',
    'due_at',
    'completed_at',
    'outcome_notes',
    'is_demo',
])]
class PatientFollowUp extends Model
{
    /** @use HasFactory<PatientFollowUpFactory> */
    use BelongsToTeam, HasFactory, SoftDeletes;

    protected $table = 'patient_followups';

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

    public function isOverdue(): bool
    {
        if (in_array($this->status, [FollowUpStatus::Completed, FollowUpStatus::Cancelled], true)) {
            return false;
        }

        return $this->due_at !== null && $this->due_at->isPast();
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
            'follow_up_type' => FollowUpType::class,
            'status' => FollowUpStatus::class,
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }
}
