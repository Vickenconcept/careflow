<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Patients\ApproveCaseCommunication;
use App\Actions\Patients\CreateCaseCommunication;
use App\Actions\Patients\ReviseCaseCommunicationDraft;
use App\Enums\CommunicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\ApproveCaseCommunicationRequest;
use App\Http\Requests\Organizations\StoreCaseCommunicationRequest;
use App\Http\Requests\Organizations\UpdateCaseCommunicationRequest;
use App\Models\CaseCommunication;
use App\Models\PatientCase;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use InvalidArgumentException;
use Throwable;

class CaseCommunicationController extends Controller
{
    public function store(
        StoreCaseCommunicationRequest $request,
        Team $current_team,
        PatientCase $case,
        CreateCaseCommunication $createCaseCommunication,
        ApproveCaseCommunication $approveCaseCommunication,
    ): RedirectResponse {
        abort_unless($case->team_id === $current_team->id, 404);

        try {
            $communication = $createCaseCommunication->handle($case, $request->user(), $request->validated());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['communication' => $exception->getMessage()]);
        }

        $sendNow = $request->boolean('send_now')
            && ! $communication->is_ai_draft
            && $communication->status === CommunicationStatus::AwaitingApproval;

        if ($sendNow) {
            $error = $this->sendApproved($approveCaseCommunication, $communication, $request);

            if ($error !== null) {
                return back()->withErrors(['communication' => $error.' The message was kept as a draft.']);
            }
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => match (true) {
                $sendNow => __('Message sent.'),
                $communication->status === CommunicationStatus::AwaitingApproval => __('Draft saved for approval.'),
                default => __('Saved to the conversation.'),
            },
        ]);

        return $this->backToCase($current_team, $case);
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

        $markSent = (bool) $request->validated('mark_sent', false);

        if ($markSent) {
            $error = $this->sendApproved($approveCaseCommunication, $communication, $request);

            if ($error !== null) {
                return back()->withErrors(['communication' => $error]);
            }
        } else {
            try {
                $approveCaseCommunication->handle($communication, $request->user());
            } catch (InvalidArgumentException $exception) {
                return back()->withErrors(['communication' => $exception->getMessage()]);
            }
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $markSent ? __('Message sent.') : __('Message approved.'),
        ]);

        return $this->backToCase($current_team, $case);
    }

    public function update(
        UpdateCaseCommunicationRequest $request,
        Team $current_team,
        PatientCase $case,
        CaseCommunication $communication,
        ReviseCaseCommunicationDraft $revise,
    ): RedirectResponse {
        abort_unless($case->team_id === $current_team->id, 404);
        abort_unless($communication->patient_case_id === $case->id, 404);

        try {
            $revise->update(
                $communication,
                $request->user(),
                (string) $request->validated('body'),
                $request->validated('subject'),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['communication' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Draft updated.')]);

        return $this->backToCase($current_team, $case);
    }

    public function destroy(
        Request $request,
        Team $current_team,
        PatientCase $case,
        CaseCommunication $communication,
        ReviseCaseCommunicationDraft $revise,
    ): RedirectResponse {
        abort_unless($case->team_id === $current_team->id, 404);
        abort_unless($communication->patient_case_id === $case->id, 404);
        Gate::authorize('manageCommunications', $case);

        try {
            $revise->discard($communication, $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['communication' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Draft discarded.')]);

        return $this->backToCase($current_team, $case);
    }

    /**
     * Approve and deliver a message. Returns a staff-facing error, or null when it was sent.
     */
    protected function sendApproved(
        ApproveCaseCommunication $approveCaseCommunication,
        CaseCommunication $communication,
        Request $request,
    ): ?string {
        try {
            $approveCaseCommunication->handle($communication, $request->user(), markSent: true);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        } catch (Throwable $exception) {
            Log::warning('Case communication could not be delivered', [
                'communication_uuid' => $communication->uuid,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return 'The message could not be sent right now. Please try again in a few minutes.';
        }

        return null;
    }

    protected function backToCase(Team $team, PatientCase $case): RedirectResponse
    {
        return to_route('organizations.cases.show', [
            'current_team' => $team->slug,
            'case' => $case->reference,
            'tab' => 'communications',
        ]);
    }
}
