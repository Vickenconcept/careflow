<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\DocumentProcessingStatus;
use Database\Factories\DocumentProcessingRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property string $provider
 * @property DocumentProcessingStatus $status
 */
#[Fillable([
    'team_id',
    'patient_document_id',
    'uuid',
    'provider',
    'status',
    'attempt',
    'started_at',
    'finished_at',
    'usage',
    'error_message',
])]
class DocumentProcessingRun extends Model
{
    /** @use HasFactory<DocumentProcessingRunFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * @return BelongsTo<PatientDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(PatientDocument::class, 'patient_document_id');
    }

    /**
     * @return HasMany<DocumentExtractedText, $this>
     */
    public function extractedTexts(): HasMany
    {
        return $this->hasMany(DocumentExtractedText::class);
    }

    /**
     * @return HasMany<DocumentExtraction, $this>
     */
    public function extractions(): HasMany
    {
        return $this->hasMany(DocumentExtraction::class);
    }

    /**
     * @return HasMany<DocumentTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(DocumentTranslation::class);
    }

    /**
     * @return HasMany<DocumentSummary, $this>
     */
    public function summaries(): HasMany
    {
        return $this->hasMany(DocumentSummary::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DocumentProcessingStatus::class,
            'attempt' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'usage' => 'array',
        ];
    }
}
