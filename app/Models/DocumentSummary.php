<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\DocumentSummaryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'team_id',
    'patient_document_id',
    'document_processing_run_id',
    'version',
    'sections',
    'corrected_sections',
    'provider',
    'disclaimer',
    'is_synthetic',
    'review_status',
    'is_current',
])]
class DocumentSummary extends Model
{
    /** @use HasFactory<DocumentSummaryFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * @return BelongsTo<PatientDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(PatientDocument::class, 'patient_document_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function effectiveSections(): array
    {
        return $this->corrected_sections ?? $this->sections;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'sections' => 'array',
            'corrected_sections' => 'array',
            'is_synthetic' => 'boolean',
            'is_current' => 'boolean',
        ];
    }
}
