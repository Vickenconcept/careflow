<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\AppointmentConfirmationSource;
use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use Database\Factories\CaseAppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property AppointmentStatus $status
 * @property AppointmentType $appointment_type
 * @property Carbon|null $proposed_starts_at
 * @property Carbon|null $confirmed_starts_at
 * @property bool $hospital_slot_verified
 */
#[Fillable([
    'team_id',
    'patient_case_id',
    'department_id',
    'doctor_id',
    'created_by_user_id',
    'confirmed_by_user_id',
    'uuid',
    'status',
    'appointment_type',
    'proposed_starts_at',
    'proposed_ends_at',
    'confirmed_starts_at',
    'confirmed_ends_at',
    'location',
    'meeting_instructions',
    'external_his_reference',
    'confirmation_source',
    'hospital_slot_verified',
    'confirmed_at',
    'notes',
    'is_demo',
])]
class CaseAppointment extends Model
{
    /** @use HasFactory<CaseAppointmentFactory> */
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
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
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
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isAuthoritativelyConfirmed(): bool
    {
        return $this->status === AppointmentStatus::Confirmed
            && $this->hospital_slot_verified === true
            && $this->confirmation_source !== null
            && $this->confirmed_starts_at !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AppointmentStatus::class,
            'appointment_type' => AppointmentType::class,
            'proposed_starts_at' => 'datetime',
            'proposed_ends_at' => 'datetime',
            'confirmed_starts_at' => 'datetime',
            'confirmed_ends_at' => 'datetime',
            'confirmation_source' => AppointmentConfirmationSource::class,
            'hospital_slot_verified' => 'boolean',
            'confirmed_at' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }
}
