<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\CaseActivityType;
use Database\Factories\CaseActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $patient_case_id
 * @property int|null $actor_user_id
 * @property CaseActivityType $activity_type
 * @property string $summary
 * @property array<string, mixed>|null $properties
 * @property bool $is_patient_visible
 * @property Carbon $occurred_at
 */
#[Fillable([
    'team_id',
    'patient_case_id',
    'actor_user_id',
    'activity_type',
    'summary',
    'properties',
    'is_patient_visible',
    'occurred_at',
])]
class CaseActivity extends Model
{
    /** @use HasFactory<CaseActivityFactory> */
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
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activity_type' => CaseActivityType::class,
            'properties' => 'array',
            'is_patient_visible' => 'boolean',
            'occurred_at' => 'datetime',
        ];
    }
}
