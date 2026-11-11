<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\ConsentType;
use App\Enums\DocumentCategory;
use App\Enums\DocumentProcessingStatus;
use App\Support\Storage\OrganizationStorage;
use Database\Factories\PatientDocumentFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property string $original_filename
 * @property string $disk
 * @property string $path
 * @property DocumentCategory $category
 * @property DocumentProcessingStatus $processing_status
 * @property Carbon $uploaded_at
 */
#[Fillable([
    'team_id',
    'patient_id',
    'patient_case_id',
    'uuid',
    'original_filename',
    'disk',
    'path',
    'mime_type',
    'extension',
    'size_bytes',
    'checksum_sha256',
    'category',
    'processing_status',
    'original_language',
    'processing_error',
    'uploaded_at',
])]
class PatientDocument extends Model
{
    /** @use HasFactory<PatientDocumentFactory> */
    use BelongsToTeam, HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<PatientCase, $this>
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(PatientCase::class, 'patient_case_id');
    }

    /**
     * @return HasMany<DocumentProcessingRun, $this>
     */
    public function processingRuns(): HasMany
    {
        return $this->hasMany(DocumentProcessingRun::class);
    }

    /**
     * @return HasOne<DocumentExtractedText, $this>
     */
    public function currentExtractedText(): HasOne
    {
        return $this->hasOne(DocumentExtractedText::class)->where('is_current', true);
    }

    /**
     * @return HasOne<DocumentExtraction, $this>
     */
    public function currentExtraction(): HasOne
    {
        return $this->hasOne(DocumentExtraction::class)->where('is_current', true);
    }

    /**
     * Why AI must not touch this document, or null when processing is permitted.
     * Requires both the hospital's AI switch and the patient's own AI consent.
     */
    public function aiProcessingBlockedReason(): ?string
    {
        if ($this->team->organizationSetting?->ai_processing_enabled === false) {
            return 'AI processing is switched off for this organization. Review the original file manually.';
        }

        $consented = PatientConsent::query()
            ->where('patient_id', $this->patient_id)
            ->where('consent_type', ConsentType::AiProcessing)
            ->where('accepted', true)
            ->exists();

        return $consented
            ? null
            : 'The patient did not consent to AI processing. Review the original file manually.';
    }

    /**
     * The filesystem holding this document: platform storage or the organization's own.
     */
    public function filesystem(): Filesystem
    {
        return app(OrganizationStorage::class)->filesystemFor($this->team, $this->disk);
    }

    /**
     * Run a callback with a temporary local copy of the stored file (for tools that need a path).
     *
     * @template T
     *
     * @param  callable(string): T  $callback
     * @return T
     */
    public function withLocalCopy(callable $callback): mixed
    {
        $temporary = tempnam(sys_get_temp_dir(), 'careflow-doc-');

        try {
            file_put_contents($temporary, $this->filesystem()->get($this->path));

            return $callback($temporary);
        } finally {
            @unlink($temporary);
        }
    }

    /**
     * @return HasOne<DocumentTranslation, $this>
     */
    public function currentTranslation(): HasOne
    {
        return $this->hasOne(DocumentTranslation::class)->where('is_current', true);
    }

    /**
     * @return HasOne<DocumentSummary, $this>
     */
    public function currentSummary(): HasOne
    {
        return $this->hasOne(DocumentSummary::class)->where('is_current', true);
    }

    /**
     * @return HasMany<DocumentReviewEvent, $this>
     */
    public function reviewEvents(): HasMany
    {
        return $this->hasMany(DocumentReviewEvent::class);
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
            'category' => DocumentCategory::class,
            'processing_status' => DocumentProcessingStatus::class,
            'size_bytes' => 'integer',
            'uploaded_at' => 'datetime',
        ];
    }
}
