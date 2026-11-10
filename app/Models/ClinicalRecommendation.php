<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\ClinicalRecommendationStatus;
use Database\Factories\ClinicalRecommendationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property ClinicalRecommendationStatus $status
 * @property bool $is_ai_generated
 * @property bool $is_current
 */
#[Fillable([
    'team_id',
    'patient_case_id',
    'doctor_review_id',
    'author_user_id',
    'parent_recommendation_id',
    'uuid',
    'version',
    'status',
    'recommendation_text',
    'supporting_document_uuids',
    'is_ai_generated',
    'is_current',
])]
class ClinicalRecommendation extends Model
{
    /** @use HasFactory<ClinicalRecommendationFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * @return BelongsTo<PatientCase, $this>
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(PatientCase::class, 'patient_case_id');
    }

    /**
     * @return BelongsTo<DoctorReview, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(DoctorReview::class, 'doctor_review_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parentRecommendation(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_recommendation_id');
    }

    /**
     * @return HasMany<ClinicalApproval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(ClinicalApproval::class);
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
            'version' => 'integer',
            'status' => ClinicalRecommendationStatus::class,
            'supporting_document_uuids' => 'array',
            'is_ai_generated' => 'boolean',
            'is_current' => 'boolean',
        ];
    }
}
