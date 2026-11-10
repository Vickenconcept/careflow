<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Patients\CreateCaseTask;
use App\Actions\Patients\TransitionCaseTask;
use App\Actions\Patients\UpdateCaseTask;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\StoreCaseTaskRequest;
use App\Http\Requests\Organizations\TransitionCaseTaskRequest;
use App\Http\Requests\Organizations\UpdateCaseTaskRequest;
use App\Models\CaseTask;
use App\Models\PatientCase;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use InvalidArgumentException;

class CaseTaskController extends Controller
{
    public function store(
        StoreCaseTaskRequest $request,
        Team $current_team,
        PatientCase $case,
        CreateCaseTask $createCaseTask,
    ): RedirectResponse {
        abort_unless($case->team_id === $current_team->id, 404);

        try {
            $createCaseTask->handle($case, $request->user(), $request->validated());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['task' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Task created.'),
        ]);

        return to_route('organizations.cases.show', [
            'current_team' => $current_team->slug,
            'case' => $case->reference,
        ]);
    }

    public function update(
        UpdateCaseTaskRequest $request,
        Team $current_team,
        PatientCase $case,
        CaseTask $task,
        UpdateCaseTask $updateCaseTask,
    ): RedirectResponse {
        abort_unless($case->team_id === $current_team->id, 404);
        abort_unless($task->patient_case_id === $case->id, 404);

        try {
            $updateCaseTask->handle($task, $request->user(), $request->validated());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['task' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Task updated.'),
        ]);

        return to_route('organizations.cases.show', [
            'current_team' => $current_team->slug,
            'case' => $case->reference,
        ]);
    }

    public function transition(
        TransitionCaseTaskRequest $request,
        Team $current_team,
        PatientCase $case,
        CaseTask $task,
        TransitionCaseTask $transitionCaseTask,
    ): RedirectResponse {
        abort_unless($case->team_id === $current_team->id, 404);
        abort_unless($task->patient_case_id === $case->id, 404);

        try {
            $transitionCaseTask->handle($task, $request->user(), $request->validated());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['task' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Task status updated.'),
        ]);

        return to_route('organizations.cases.show', [
            'current_team' => $current_team->slug,
            'case' => $case->reference,
        ]);
    }
}
