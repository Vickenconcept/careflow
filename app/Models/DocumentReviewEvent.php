<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\DocumentReviewEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'team_id',
    'patient_document_id',
    'user_id',
    'target_type',
    'target_id',
    'field_path',
    'previous_value',
    'new_value',
    'reason',
    'reviewed_at',
])]
class DocumentReviewEvent extends Model
{
    /** @use HasFactory<DocumentReviewEventFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * @return BelongsTo<PatientDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(PatientDocument::class, 'patient_document_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function target(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'target_type', 'target_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'previous_value' => 'array',
            'new_value' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }
}
