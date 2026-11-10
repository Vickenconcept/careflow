<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\DocumentTranslationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'team_id',
    'patient_document_id',
    'document_processing_run_id',
    'version',
    'source_language',
    'target_language',
    'translated_text',
    'provider',
    'status',
    'disclaimer',
    'is_synthetic',
    'is_current',
    'reviewed_by',
    'review_outcome',
    'translated_at',
])]
class DocumentTranslation extends Model
{
    /** @use HasFactory<DocumentTranslationFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * @return BelongsTo<PatientDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(PatientDocument::class, 'patient_document_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'is_synthetic' => 'boolean',
            'is_current' => 'boolean',
            'translated_at' => 'datetime',
        ];
    }
}
