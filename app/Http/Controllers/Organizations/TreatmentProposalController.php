<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Patients\CreateTreatmentProposal;
use App\Actions\Patients\DecideTreatmentProposal;
use App\Actions\Patients\RecordTreatmentProposalPatientResponse;
use App\Actions\Patients\SendTreatmentProposal;
use App\Actions\Patients\SubmitTreatmentProposalForApproval;
use App\Actions\Patients\UpdateTreatmentProposal;
use App\Enums\TreatmentProposalPatientResponse;
use App\Exceptions\InvalidTreatmentProposalTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\StoreTreatmentProposalRequest;
use App\Http\Requests\Organizations\TransitionTreatmentProposalRequest;
use App\Http\Requests\Organizations\UpdateTreatmentProposalRequest;
use App\Models\PatientCase;
use App\Models\Team;
use App\Models\TreatmentProposal;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use InvalidArgumentException;

class TreatmentProposalController extends Controller
{
    public function store(
        StoreTreatmentProposalRequest $request,
        Team $current_team,
        PatientCase $case,
        CreateTreatmentProposal $createTreatmentProposal,
    ): RedirectResponse {
        abort_unless($case->team_id === $current_team->id, 404);

        try {
            $createTreatmentProposal->handle(
                case: $case,
                actor: $request->user(),
                data: $request->validated(),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['proposed_procedure' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Treatment proposal draft created.'),
        ]);

        return to_route('organizations.cases.show', [
            'current_team' => $current_team->slug,
            'case' => $case->reference,
        ]);
    }

    public function update(
        UpdateTreatmentProposalRequest $request,
        Team $current_team,
        PatientCase $case,
        TreatmentProposal $proposal,
        UpdateTreatmentProposal $updateTreatmentProposal,
    ): RedirectResponse {
        abort_unless($case->team_id === $current_team->id, 404);
        abort_unless($proposal->patient_case_id === $case->id, 404);

        try {
            $updateTreatmentProposal->handle(
                proposal: $proposal,
                actor: $request->user(),
                data: $request->validated(),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['proposed_procedure' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Treatment proposal updated.'),
        ]);

        return to_route('organizations.cases.show', [
            'current_team' => $current_team->slug,
            'case' => $case->reference,
        ]);
    }

    public function transition(
        TransitionTreatmentProposalRequest $request,
        Team $current_team,
        PatientCase $case,
        TreatmentProposal $proposal,
        SubmitTreatmentProposalForApproval $submit,
        DecideTreatmentProposal $decide,
        SendTreatmentProposal $send,
        RecordTreatmentProposalPatientResponse $recordResponse,
    ): RedirectResponse {
        abort_unless($case->team_id === $current_team->id, 404);
        abort_unless($proposal->patient_case_id === $case->id, 404);

        $action = $request->validated('action');

        try {
            match ($action) {
                'submit_for_approval' => $submit->handle($proposal, $request->user()),
                'approve' => $decide->handle($proposal, $request->user(), true, $request->validated('notes')),
                'return_to_draft' => $decide->handle($proposal, $request->user(), false, $request->validated('notes')),
                'send' => $send->handle($proposal, $request->user()),
                'record_patient_response' => $recordResponse->handle(
                    proposal: $proposal,
                    actor: $request->user(),
                    response: TreatmentProposalPatientResponse::from($request->validated('patient_response')),
                    notes: $request->validated('notes'),
                ),
            };
        } catch (InvalidArgumentException|InvalidTreatmentProposalTransitionException $exception) {
            return back()->withErrors(['proposal' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Treatment proposal updated.'),
        ]);

        return to_route('organizations.cases.show', [
            'current_team' => $current_team->slug,
            'case' => $case->reference,
        ]);
    }
}
