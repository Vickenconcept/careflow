<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\TreatmentProposalPatientResponse;
use App\Enums\TreatmentProposalStatus;
use Database\Factories\TreatmentProposalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property string $reference
 * @property TreatmentProposalStatus $status
 * @property bool $is_current
 * @property Carbon|null $valid_until
 */
#[Fillable([
    'team_id',
    'patient_case_id',
    'department_id',
    'responsible_physician_id',
    'clinical_recommendation_id',
    'created_by_user_id',
    'parent_proposal_id',
    'uuid',
    'reference',
    'version',
    'status',
    'proposed_procedure',
    'estimated_cost',
    'currency',
    'expected_duration',
    'required_appointments',
    'additional_information',
    'valid_until',
    'submitted_for_approval_at',
    'approved_at',
    'approved_by_user_id',
    'sent_at',
    'sent_by_user_id',
    'patient_responded_at',
    'patient_response',
    'decision_notes',
    'is_current',
    'is_demo',
])]
class TreatmentProposal extends Model
{
    /** @use HasFactory<TreatmentProposalFactory> */
    use BelongsToTeam, HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<PatientCase, $this>
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(PatientCase::class, 'patient_case_id');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responsiblePhysician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_physician_id');
    }

    /**
     * @return BelongsTo<ClinicalRecommendation, $this>
     */
    public function clinicalRecommendation(): BelongsTo
    {
        return $this->belongsTo(ClinicalRecommendation::class);
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
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parentProposal(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_proposal_id');
    }

    /**
     * @return HasMany<TreatmentProposalItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(TreatmentProposalItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isExpired(): bool
    {
        return $this->valid_until !== null && $this->valid_until->isPast();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'status' => TreatmentProposalStatus::class,
            'estimated_cost' => 'decimal:2',
            'required_appointments' => 'integer',
            'valid_until' => 'date',
            'submitted_for_approval_at' => 'datetime',
            'approved_at' => 'datetime',
            'sent_at' => 'datetime',
            'patient_responded_at' => 'datetime',
            'patient_response' => TreatmentProposalPatientResponse::class,
            'is_current' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }
}
