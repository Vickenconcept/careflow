<?php

namespace App\Actions\Patients;

use App\Enums\CaseActivityType;
use App\Models\CaseActivity;
use App\Models\PatientCase;
use App\Models\User;

class RecordCaseActivity
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function handle(
        PatientCase $case,
        CaseActivityType $type,
        string $summary,
        ?User $actor = null,
        array $properties = [],
        bool $patientVisible = false,
    ): CaseActivity {
        return CaseActivity::query()->create([
            'team_id' => $case->team_id,
            'patient_case_id' => $case->id,
            'actor_user_id' => $actor?->id,
            'activity_type' => $type,
            'summary' => $summary,
            'properties' => $properties === [] ? null : $properties,
            'is_patient_visible' => $patientVisible,
            'occurred_at' => now(),
        ]);
    }
}
