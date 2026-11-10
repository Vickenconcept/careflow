<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\PatientEnquiryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $patient_id
 * @property int|null $department_id
 * @property string $submission_uuid
 * @property string $preferred_language
 * @property string $reason_for_contact
 * @property Carbon $submitted_at
 */
#[Fillable([
    'team_id',
    'patient_id',
    'department_id',
    'submission_uuid',
    'preferred_language',
    'reason_for_contact',
    'diagnosis',
    'symptoms_or_history',
    'previous_procedures',
    'current_medications',
    'known_allergies',
    'additional_comments',
    'source_ip',
    'user_agent',
    'submitted_at',
])]
class PatientEnquiry extends Model
{
    /** @use HasFactory<PatientEnquiryFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return HasOne<PatientCase, $this>
     */
    public function case(): HasOne
    {
        return $this->hasOne(PatientCase::class);
    }

    /**
     * @return HasMany<PatientConsent, $this>
     */
    public function consents(): HasMany
    {
        return $this->hasMany(PatientConsent::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }
}
