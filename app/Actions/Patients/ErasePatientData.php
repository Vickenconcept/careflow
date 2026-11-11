<?php

namespace App\Actions\Patients;

use App\Enums\CaseActivityType;
use App\Models\CaseCommunication;
use App\Models\Patient;
use App\Models\PatientDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ErasePatientData
{
    public function __construct(private RecordCaseActivity $recordActivity) {}

    /**
     * Irreversibly remove a patient's personal data while keeping the case shells
     * (reference, status, dates) so reporting and the audit trail stay intact.
     */
    public function handle(Patient $patient, User $actor): void
    {
        $patient->loadMissing(['cases', 'documents']);

        foreach ($patient->documents as $document) {
            $this->deleteStoredFile($document);
        }

        DB::transaction(function () use ($patient, $actor): void {
            $caseIds = $patient->cases->pluck('id');

            $patient->documents()->each(fn (PatientDocument $document) => $document->forceDelete());

            CaseCommunication::query()
                ->whereIn('patient_case_id', $caseIds)
                ->update(['subject' => null, 'body' => '[erased]', 'sender' => null, 'recipient' => null]);

            $patient->enquiries()->update([
                'reason_for_contact' => '[erased]',
                'diagnosis' => null,
                'symptoms_or_history' => null,
                'previous_procedures' => null,
                'current_medications' => null,
                'known_allergies' => null,
                'additional_comments' => null,
                'source_ip' => null,
                'user_agent' => null,
            ]);

            $patient->cases()->update([
                'portal_access_token_hash' => null,
                'portal_access_token_expires_at' => null,
            ]);

            $patient->forceFill([
                'first_name' => 'Erased',
                'last_name' => 'Patient',
                'email' => 'erased-'.$patient->id.'@erased.invalid',
                'phone' => null,
                'date_of_birth' => null,
                'gender' => null,
            ])->save();

            foreach ($patient->cases as $case) {
                $this->recordActivity->handle(
                    case: $case,
                    type: CaseActivityType::DataRetentionAction,
                    summary: 'Patient personal data erased',
                    actor: $actor,
                );
            }
        });
    }

    public function deleteStoredFile(PatientDocument $document): void
    {
        if ($document->path !== null && $document->path !== '') {
            $document->filesystem()->delete($document->path);
        }
    }
}
