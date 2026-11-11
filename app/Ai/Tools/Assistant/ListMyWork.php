<?php

namespace App\Ai\Tools\Assistant;

use App\Enums\CaseTaskStatus;
use App\Enums\FollowUpStatus;
use App\Models\CaseTask;
use App\Models\PatientFollowUp;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListMyWork extends AssistantTool
{
    public static function label(): string
    {
        return 'My tasks and follow-ups';
    }

    public function description(): Stringable|string
    {
        return "List the user's own open tasks and follow-ups, soonest due first, with overdue items flagged.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Stringable|string
    {
        $visibleCaseIds = $this->visibleCases()->select('patient_cases.id');

        $tasks = CaseTask::query()
            ->where('team_id', $this->team->id)
            ->where('assigned_user_id', $this->user->id)
            ->whereIn('status', [CaseTaskStatus::Open, CaseTaskStatus::InProgress, CaseTaskStatus::Waiting])
            ->whereIn('patient_case_id', $visibleCaseIds)
            ->with('case.patient')
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->limit(20)
            ->get();

        $followUps = PatientFollowUp::query()
            ->where('team_id', $this->team->id)
            ->where('assigned_user_id', $this->user->id)
            ->whereIn('status', [FollowUpStatus::Scheduled, FollowUpStatus::InProgress])
            ->whereIn('patient_case_id', $this->visibleCases()->select('patient_cases.id'))
            ->with('case.patient')
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->limit(20)
            ->get();

        return $this->json([
            'tasks' => $tasks->map(fn (CaseTask $task) => [
                'title' => $task->title,
                'case' => $task->case?->reference,
                'patient' => $task->case?->patient?->fullName(),
                'priority' => $task->priority->label(),
                'status' => $task->status->label(),
                'due' => $task->due_at?->toDateString(),
                'overdue' => $task->due_at?->isPast() ?? false,
            ])->all(),
            'follow_ups' => $followUps->map(fn (PatientFollowUp $followUp) => [
                'title' => $followUp->title,
                'case' => $followUp->case?->reference,
                'patient' => $followUp->case?->patient?->fullName(),
                'status' => $followUp->status->label(),
                'due' => $followUp->due_at?->toDateString(),
                'overdue' => $followUp->due_at?->isPast() ?? false,
            ])->all(),
        ]);
    }
}
