<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\ConsentType;
use Database\Factories\PatientConsentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property ConsentType $consent_type
 * @property bool $accepted
 * @property Carbon $consented_at
 */
#[Fillable([
    'team_id',
    'patient_id',
    'patient_enquiry_id',
    'submission_uuid',
    'consent_type',
    'privacy_notice_version',
    'consent_text_version',
    'accepted',
    'consented_at',
    'source_ip',
])]
class PatientConsent extends Model
{
    /** @use HasFactory<PatientConsentFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<PatientEnquiry, $this>
     */
    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(PatientEnquiry::class, 'patient_enquiry_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'consent_type' => ConsentType::class,
            'accepted' => 'boolean',
            'consented_at' => 'datetime',
        ];
    }
}
