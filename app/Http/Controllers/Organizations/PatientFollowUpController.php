<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Patients\CompletePatientFollowUp;
use App\Actions\Patients\CreatePatientFollowUp;
use App\Actions\Patients\UpdatePatientFollowUp;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\CompletePatientFollowUpRequest;
use App\Http\Requests\Organizations\StorePatientFollowUpRequest;
use App\Http\Requests\Organizations\UpdatePatientFollowUpRequest;
use App\Models\PatientCase;
use App\Models\PatientFollowUp;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use InvalidArgumentException;

class PatientFollowUpController extends Controller
{
    public function store(
        StorePatientFollowUpRequest $request,
        Team $current_team,
        PatientCase $case,
        CreatePatientFollowUp $createPatientFollowUp,
    ): RedirectResponse {
        abort_unless($case->team_id === $current_team->id, 404);

        try {
            $createPatientFollowUp->handle($case, $request->user(), $request->validated());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['follow_up' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Follow-up scheduled.'),
        ]);

        return to_route('organizations.cases.show', [
            'current_team' => $current_team->slug,
            'case' => $case->reference,
        ]);
    }

    public function update(
        UpdatePatientFollowUpRequest $request,
        Team $current_team,
        PatientCase $case,
        PatientFollowUp $followUp,
        UpdatePatientFollowUp $updatePatientFollowUp,
    ): RedirectResponse {
        abort_unless($case->team_id === $current_team->id, 404);
        abort_unless($followUp->patient_case_id === $case->id, 404);

        try {
            $updatePatientFollowUp->handle($followUp, $request->user(), $request->validated());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['follow_up' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Follow-up updated.'),
        ]);

        return to_route('organizations.cases.show', [
            'current_team' => $current_team->slug,
            'case' => $case->reference,
        ]);
    }

    public function complete(
        CompletePatientFollowUpRequest $request,
        Team $current_team,
        PatientCase $case,
        PatientFollowUp $followUp,
        CompletePatientFollowUp $completePatientFollowUp,
    ): RedirectResponse {
        abort_unless($case->team_id === $current_team->id, 404);
        abort_unless($followUp->patient_case_id === $case->id, 404);

        try {
            $completePatientFollowUp->handle(
                followUp: $followUp,
                actor: $request->user(),
                outcomeNotes: $request->validated('outcome_notes'),
                cancel: (bool) $request->validated('cancel', false),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['follow_up' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Follow-up closed.'),
        ]);

        return to_route('organizations.cases.show', [
            'current_team' => $current_team->slug,
            'case' => $case->reference,
        ]);
    }
}
