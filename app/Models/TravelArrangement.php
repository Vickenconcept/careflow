<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\TravelArrangementStatus;
use App\Enums\VisaAssistanceStatus;
use Database\Factories\TravelArrangementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'team_id',
    'patient_case_id',
    'assigned_coordinator_id',
    'updated_by_user_id',
    'uuid',
    'status',
    'expected_arrival_date',
    'expected_departure_date',
    'flight_information',
    'airport_pickup_required',
    'airport_pickup_details',
    'transfer_arrangements',
    'accommodation',
    'hotel_details',
    'visa_assistance_status',
    'special_administrative_requirements',
    'checklist',
    'notes',
    'is_demo',
])]
class TravelArrangement extends Model
{
    /** @use HasFactory<TravelArrangementFactory> */
    use BelongsToTeam, HasFactory, SoftDeletes;

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
    public function assignedCoordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_coordinator_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
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
            'status' => TravelArrangementStatus::class,
            'expected_arrival_date' => 'date',
            'expected_departure_date' => 'date',
            'airport_pickup_required' => 'boolean',
            'visa_assistance_status' => VisaAssistanceStatus::class,
            'checklist' => 'array',
            'is_demo' => 'boolean',
        ];
    }
}
