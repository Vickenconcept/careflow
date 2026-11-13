<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Patients\CreateCaseAppointment;
use App\Actions\Patients\NotifyPatient;
use App\Actions\Patients\TransitionCaseAppointment;
use App\Actions\Patients\UpdateCaseAppointment;
use App\Exceptions\InvalidAppointmentTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\StoreCaseAppointmentRequest;
use App\Http\Requests\Organizations\TransitionCaseAppointmentRequest;
use App\Http\Requests\Organizations\UpdateCaseAppointmentRequest;
use App\Models\CaseAppointment;
use App\Models\PatientCase;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use InvalidArgumentException;

class CaseAppointmentController extends Controller
{
    public function store(
        StoreCaseAppointmentRequest $request,
        Team $current_team,
        PatientCase $case,
        CreateCaseAppointment $createCaseAppointment,
    ): RedirectResponse {
        abort_unless($case->team_id === $current_team->id, 404);

        try {
            $createCaseAppointment->handle(
                case: $case,
                actor: $request->user(),
                data: $request->validated(),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['appointment' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Appointment proposed.'),
        ]);

        return to_route('organizations.cases.show', [
            'current_team' => $current_team->slug,
            'case' => $case->reference,
        ]);
    }

    public function update(
        UpdateCaseAppointmentRequest $request,
        Team $current_team,
        PatientCase $case,
        CaseAppointment $appointment,
        UpdateCaseAppointment $updateCaseAppointment,
    ): RedirectResponse {
        abort_unless($case->team_id === $current_team->id, 404);
        abort_unless($appointment->patient_case_id === $case->id, 404);

        try {
            $updateCaseAppointment->handle(
                appointment: $appointment,
                actor: $request->user(),
                data: $request->validated(),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['appointment' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Appointment updated.'),
        ]);

        return to_route('organizations.cases.show', [
            'current_team' => $current_team->slug,
            'case' => $case->reference,
        ]);
    }

    public function transition(
        TransitionCaseAppointmentRequest $request,
        Team $current_team,
        PatientCase $case,
        CaseAppointment $appointment,
        TransitionCaseAppointment $transitionCaseAppointment,
    ): RedirectResponse {
        abort_unless($case->team_id === $current_team->id, 404);
        abort_unless($appointment->patient_case_id === $case->id, 404);

        try {
            $transitionCaseAppointment->handle(
                appointment: $appointment,
                actor: $request->user(),
                data: $request->validated(),
            );
        } catch (InvalidArgumentException|InvalidAppointmentTransitionException $exception) {
            return back()->withErrors(['appointment' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trim(__('Appointment status updated.').' '.(app(NotifyPatient::class)->staffSummary() ?? '')),
        ]);

        return to_route('organizations.cases.show', [
            'current_team' => $current_team->slug,
            'case' => $case->reference,
        ]);
    }
}
