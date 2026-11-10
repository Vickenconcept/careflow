<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\DocumentExtractionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'team_id',
    'patient_document_id',
    'document_processing_run_id',
    'version',
    'schema_version',
    'payload',
    'corrected_payload',
    'provider',
    'is_synthetic',
    'review_status',
    'is_current',
])]
class DocumentExtraction extends Model
{
    /** @use HasFactory<DocumentExtractionFactory> */
    use BelongsToTeam, HasFactory;

    protected $table = 'document_extractions';

    /**
     * @return BelongsTo<PatientDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(PatientDocument::class, 'patient_document_id');
    }

    /**
     * Effective payload after human corrections.
     *
     * @return array<string, mixed>
     */
    public function effectivePayload(): array
    {
        return $this->corrected_payload ?? $this->payload;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'payload' => 'array',
            'corrected_payload' => 'array',
            'is_synthetic' => 'boolean',
            'is_current' => 'boolean',
        ];
    }
}
