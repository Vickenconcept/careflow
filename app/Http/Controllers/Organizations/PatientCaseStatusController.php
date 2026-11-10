<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Patients\TransitionPatientCaseStatus;
use App\Enums\CaseStatus;
use App\Exceptions\InvalidCaseStatusTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\TransitionPatientCaseStatusRequest;
use App\Models\PatientCase;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PatientCaseStatusController extends Controller
{
    public function update(
        TransitionPatientCaseStatusRequest $request,
        Team $current_team,
        PatientCase $case,
        TransitionPatientCaseStatus $transition,
    ): RedirectResponse {
        abort_unless($case->team_id === $current_team->id, 404);

        try {
            $transition->handle(
                case: $case,
                to: CaseStatus::from($request->validated('status')),
                actor: $request->user(),
                note: $request->validated('note'),
            );
        } catch (InvalidCaseStatusTransitionException $exception) {
            return back()->withErrors([
                'status' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Case status updated.'),
        ]);

        return back();
    }
}
