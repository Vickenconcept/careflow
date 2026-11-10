<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\ClinicalApprovalOutcome;
use Database\Factories\ClinicalApprovalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'team_id',
    'clinical_recommendation_id',
    'approver_user_id',
    'outcome',
    'notes',
    'decided_at',
])]
class ClinicalApproval extends Model
{
    /** @use HasFactory<ClinicalApprovalFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * @return BelongsTo<ClinicalRecommendation, $this>
     */
    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(ClinicalRecommendation::class, 'clinical_recommendation_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcome' => ClinicalApprovalOutcome::class,
            'decided_at' => 'datetime',
        ];
    }
}
