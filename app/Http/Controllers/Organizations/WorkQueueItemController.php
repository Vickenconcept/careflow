<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Patients\CompletePatientFollowUp;
use App\Actions\Patients\TransitionCaseTask;
use App\Enums\CaseTaskStatus;
use App\Http\Controllers\Controller;
use App\Models\CaseTask;
use App\Models\PatientCase;
use App\Models\PatientFollowUp;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use InvalidArgumentException;

/**
 * Quick "tick it off" actions on My work. Same rules as the case page, but the
 * staff member stays on My work afterwards.
 */
class WorkQueueItemController extends Controller
{
    public function completeTask(
        Request $request,
        Team $current_team,
        PatientCase $case,
        CaseTask $task,
        TransitionCaseTask $transitionCaseTask,
    ): RedirectResponse {
        abort_unless($case->team_id === $current_team->id, 404);
        abort_unless($task->patient_case_id === $case->id, 404);
        Gate::authorize('manageTasks', $case);

        try {
            $transitionCaseTask->handle($task, $request->user(), [
                'status' => CaseTaskStatus::Completed->value,
                'comment' => null,
            ]);
        } catch (InvalidArgumentException) {
            return back()->withErrors(['task' => __('This task is already closed.')]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Task done: :title', ['title' => $task->title]),
        ]);

        return to_route('organizations.work', ['current_team' => $current_team->slug]);
    }

    public function completeFollowUp(
        Request $request,
        Team $current_team,
        PatientCase $case,
        PatientFollowUp $followUp,
        CompletePatientFollowUp $completePatientFollowUp,
    ): RedirectResponse {
        abort_unless($case->team_id === $current_team->id, 404);
        abort_unless($followUp->patient_case_id === $case->id, 404);
        Gate::authorize('manageTasks', $case);

        try {
            $completePatientFollowUp->handle($followUp, $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['follow_up' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Follow-up done: :title', ['title' => $followUp->title]),
        ]);

        return to_route('organizations.work', ['current_team' => $current_team->slug]);
    }
}
