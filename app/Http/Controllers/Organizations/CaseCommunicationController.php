<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Patients\ApproveCaseCommunication;
use App\Actions\Patients\CreateCaseCommunication;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\ApproveCaseCommunicationRequest;
use App\Http\Requests\Organizations\StoreCaseCommunicationRequest;
use App\Models\CaseCommunication;
use App\Models\PatientCase;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use InvalidArgumentException;

class CaseCommunicationController extends Controller
{
    public function store(
        StoreCaseCommunicationRequest $request,
        Team $current_team,
        PatientCase $case,
        CreateCaseCommunication $createCaseCommunication,
    ): RedirectResponse {
        abort_unless($case->team_id === $current_team->id, 404);

        try {
            $createCaseCommunication->handle($case, $request->user(), $request->validated());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['communication' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Communication saved.'),
        ]);

        return to_route('organizations.cases.show', [
            'current_team' => $current_team->slug,
            'case' => $case->reference,
        ]);
    }

    public function approve(
        ApproveCaseCommunicationRequest $request,
        Team $current_team,
        PatientCase $case,
        CaseCommunication $communication,
        ApproveCaseCommunication $approveCaseCommunication,
    ): RedirectResponse {
        abort_unless($case->team_id === $current_team->id, 404);
        abort_unless($communication->patient_case_id === $case->id, 404);

        try {
            $approveCaseCommunication->handle(
                communication: $communication,
                actor: $request->user(),
                markSent: (bool) $request->validated('mark_sent', false),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['communication' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Communication approved.'),
        ]);

        return to_route('organizations.cases.show', [
            'current_team' => $current_team->slug,
            'case' => $case->reference,
        ]);
    }
}
