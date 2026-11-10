<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Patients\AssignPatientCase;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\AssignPatientCaseRequest;
use App\Models\PatientCase;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use InvalidArgumentException;

class PatientCaseAssignmentController extends Controller
{
    public function update(
        AssignPatientCaseRequest $request,
        Team $current_team,
        PatientCase $case,
        AssignPatientCase $assignPatientCase,
    ): RedirectResponse {
        abort_unless($case->team_id === $current_team->id, 404);

        $validated = $request->validated();

        try {
            $assignPatientCase->handle(
                case: $case,
                actor: $request->user(),
                coordinator: isset($validated['coordinator_id'])
                    ? User::query()->findOrFail($validated['coordinator_id'])
                    : null,
                doctor: isset($validated['doctor_id'])
                    ? User::query()->findOrFail($validated['doctor_id'])
                    : null,
                clearCoordinator: (bool) ($validated['clear_coordinator'] ?? false),
                clearDoctor: (bool) ($validated['clear_doctor'] ?? false),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors([
                'assignment' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Case assignment updated.'),
        ]);

        return back();
    }
}
