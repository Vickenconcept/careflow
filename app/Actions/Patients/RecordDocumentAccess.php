<?php

namespace App\Actions\Patients;

use App\Models\DocumentAccessLog;
use App\Models\PatientCase;
use App\Models\PatientDocument;
use App\Models\User;

class RecordDocumentAccess
{
    /**
     * @param  'view'|'preview'|'download'  $action
     */
    public function handle(PatientDocument $document, PatientCase $case, User $user, string $action, ?string $ip = null): DocumentAccessLog
    {
        $recent = DocumentAccessLog::query()
            ->where('patient_document_id', $document->id)
            ->where('user_id', $user->id)
            ->where('action', $action)
            ->where('accessed_at', '>=', now()->subMinute())
            ->first();

        if ($recent !== null && $action !== 'download') {
            return $recent;
        }

        return DocumentAccessLog::query()->create([
            'team_id' => $document->team_id,
            'patient_document_id' => $document->id,
            'patient_case_id' => $case->id,
            'user_id' => $user->id,
            'action' => $action,
            'document_filename' => $document->original_filename,
            'case_reference' => $case->reference,
            'ip_address' => $ip,
            'accessed_at' => now(),
        ]);
    }
}
