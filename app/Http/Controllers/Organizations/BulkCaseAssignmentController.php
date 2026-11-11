<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Patients\AssignPatientCase;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\BulkAssignCasesRequest;
use App\Models\PatientCase;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use InvalidArgumentException;

class BulkCaseAssignmentController extends Controller
{
    /**
     * Assign several visible cases to a coordinator and/or doctor in one go.
     */
    public function __invoke(
        BulkAssignCasesRequest $request,
        Team $current_team,
        AssignPatientCase $assignPatientCase,
    ): RedirectResponse {
        $validated = $request->validated();

        $coordinator = isset($validated['coordinator_id']) ? User::query()->findOrFail($validated['coordinator_id']) : null;
        $doctor = isset($validated['doctor_id']) ? User::query()->findOrFail($validated['doctor_id']) : null;
        $clearCoordinator = (bool) ($validated['clear_coordinator'] ?? false);
        $clearDoctor = (bool) ($validated['clear_doctor'] ?? false);

        if ($coordinator === null && $doctor === null && ! $clearCoordinator && ! $clearDoctor) {
            return back()->withErrors(['assignment' => 'Choose a coordinator or a doctor to assign.']);
        }

        $cases = PatientCase::query()
            ->visibleToStaff($request->user(), $current_team)
            ->whereIn('patient_cases.id', $validated['case_ids'])
            ->get();

        $updated = 0;
        $failure = null;

        foreach ($cases as $case) {
            try {
                $assignPatientCase->handle(
                    case: $case,
                    actor: $request->user(),
                    coordinator: $coordinator,
                    doctor: $doctor,
                    clearCoordinator: $clearCoordinator,
                    clearDoctor: $clearDoctor,
                );
                $updated++;
            } catch (InvalidArgumentException $exception) {
                $failure = $exception->getMessage();

                break;
            }
        }

        if ($failure !== null && $updated === 0) {
            return back()->withErrors(['assignment' => $failure]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trans_choice('{1} :count case updated.|[2,*] :count cases updated.', $updated, ['count' => $updated]),
        ]);

        return back();
    }
}
