<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\DoctorReviewOutcome;
use App\Enums\DoctorReviewStatus;
use Database\Factories\DoctorReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property DoctorReviewStatus $status
 * @property DoctorReviewOutcome|null $outcome
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 */
#[Fillable([
    'team_id',
    'patient_case_id',
    'reviewer_user_id',
    'uuid',
    'status',
    'findings',
    'additional_information_request',
    'outcome',
    'started_at',
    'completed_at',
    'is_demo',
])]
class DoctorReview extends Model
{
    /** @use HasFactory<DoctorReviewFactory> */
    use BelongsToTeam, HasFactory;

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
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }

    /**
     * @return HasMany<ClinicalObservation, $this>
     */
    public function observations(): HasMany
    {
        return $this->hasMany(ClinicalObservation::class);
    }

    /**
     * @return HasMany<ClinicalRecommendation, $this>
     */
    public function recommendations(): HasMany
    {
        return $this->hasMany(ClinicalRecommendation::class);
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
            'status' => DoctorReviewStatus::class,
            'outcome' => DoctorReviewOutcome::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }
}
