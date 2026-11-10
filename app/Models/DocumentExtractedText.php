<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'team_id',
    'patient_document_id',
    'document_processing_run_id',
    'version',
    'extracted_text',
    'provider',
    'is_synthetic',
    'warnings',
    'is_current',
])]
class DocumentExtractedText extends Model
{
    use BelongsToTeam;

    protected $table = 'document_extracted_texts';

    /**
     * @return BelongsTo<PatientDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(PatientDocument::class, 'patient_document_id');
    }

    /**
     * @return BelongsTo<DocumentProcessingRun, $this>
     */
    public function processingRun(): BelongsTo
    {
        return $this->belongsTo(DocumentProcessingRun::class, 'document_processing_run_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'is_synthetic' => 'boolean',
            'warnings' => 'array',
            'is_current' => 'boolean',
        ];
    }
}
