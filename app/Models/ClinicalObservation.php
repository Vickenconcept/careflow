<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\ClinicalObservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'team_id',
    'patient_case_id',
    'doctor_review_id',
    'author_user_id',
    'body',
    'visibility',
    'observed_at',
])]
class ClinicalObservation extends Model
{
    /** @use HasFactory<ClinicalObservationFactory> */
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',
        ];
    }
}
