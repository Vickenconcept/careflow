<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\DocumentAccessLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable record of who opened or downloaded a patient document.
 * Filename and case reference are snapshotted so the trail survives deletions.
 */
#[Fillable([
    'team_id',
    'patient_document_id',
    'patient_case_id',
    'user_id',
    'action',
    'document_filename',
    'case_reference',
    'ip_address',
    'accessed_at',
])]
class DocumentAccessLog extends Model
{
    /** @use HasFactory<DocumentAccessLogFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accessed_at' => 'datetime',
        ];
    }
}
