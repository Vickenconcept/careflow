<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\CommunicationChannel;
use App\Enums\CommunicationDirection;
use App\Enums\CommunicationStatus;
use Database\Factories\CaseCommunicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'team_id',
    'patient_case_id',
    'created_by_user_id',
    'approved_by_user_id',
    'uuid',
    'channel',
    'direction',
    'status',
    'language',
    'subject',
    'body',
    'sender',
    'recipient',
    'provider_reference',
    'is_ai_draft',
    'requires_approval',
    'approved_at',
    'sent_at',
    'ai_metadata',
    'is_demo',
])]
class CaseCommunication extends Model
{
    /** @use HasFactory<CaseCommunicationFactory> */
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
            'channel' => CommunicationChannel::class,
            'direction' => CommunicationDirection::class,
            'status' => CommunicationStatus::class,
            'is_ai_draft' => 'boolean',
            'requires_approval' => 'boolean',
            'approved_at' => 'datetime',
            'sent_at' => 'datetime',
            'ai_metadata' => 'array',
            'is_demo' => 'boolean',
        ];
    }
}
