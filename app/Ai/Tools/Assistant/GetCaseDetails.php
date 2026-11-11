<?php

namespace App\Ai\Tools\Assistant;

use App\Domains\Cases\CaseStatusTransitionMap;
use App\Enums\CaseStatus;
use App\Enums\CaseTaskStatus;
use App\Enums\TeamPermission;
use App\Models\CaseActivity;
use App\Models\CaseTask;
use App\Models\ClinicalRecommendation;
use App\Models\PatientDocument;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetCaseDetails extends AssistantTool
{
    public static function label(): string
    {
        return 'Read a case';
    }

    public function description(): Stringable|string
    {
        return 'Get the full picture of one case by its reference (for example CF-2026-000012): patient, status, team, documents, open tasks, recent activity and which statuses it can move to next.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'reference' => $schema->string()->description('The case reference, for example CF-2026-000012.')->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $reference = (string) ($request['reference'] ?? '');
        $case = $this->findCase($reference);

        if ($case === null) {
            return $this->notFound($reference);
        }

        $case->load(['patient', 'department', 'assignedCoordinator', 'assignedDoctor', 'enquiry']);

        $canSeeDocuments = $this->user->hasTeamPermission($this->team, TeamPermission::ViewDocuments);
        $canSeeClinical = Gate::forUser($this->user)->allows('viewClinical', $case);
        $canChangeStatus = Gate::forUser($this->user)->allows('updateStatus', $case);

        $details = [
            'reference' => $case->reference,
            'url' => $this->caseUrl($case),
            'patient' => [
                'name' => $case->patient?->fullName(),
                'country' => $case->patient?->country_of_residence,
                'language' => $case->patient?->preferred_language,
            ],
            'status' => $case->status->label(),
            'status_value' => $case->status->value,
            'priority' => $case->priority->label(),
            'department' => $case->department?->name,
            'coordinator' => $case->assignedCoordinator?->name,
            'doctor' => $case->assignedDoctor?->name,
            'reason_for_contact' => $case->enquiry?->reason_for_contact,
            'open_tasks' => $case->tasks()
                ->whereIn('status', [CaseTaskStatus::Open, CaseTaskStatus::InProgress, CaseTaskStatus::Waiting])
                ->limit(10)
                ->get()
                ->map(fn (CaseTask $task) => [
                    'title' => $task->title,
                    'priority' => $task->priority->label(),
                    'due' => $task->due_at?->toDateString(),
                    'overdue' => $task->due_at?->isPast() ?? false,
                ])->all(),
            'recent_activity' => $case->activities()
                ->latest('occurred_at')
                ->limit(6)
                ->get()
                ->map(fn (CaseActivity $activity) => [
                    'when' => $activity->occurred_at?->diffForHumans(),
                    'what' => $activity->summary,
                ])->all(),
        ];

        if ($canSeeDocuments) {
            $details['documents'] = $case->documents()
                ->latest('uploaded_at')
                ->limit(15)
                ->get()
                ->map(fn (PatientDocument $document) => [
                    'file' => $document->original_filename,
                    'type' => $document->category->label(),
                    'ai_status' => $document->processing_status->label(),
                ])->all();
        }

        if ($canSeeClinical) {
            $details['clinical_recommendations'] = $case->clinicalRecommendations()
                ->where('is_current', true)
                ->get()
                ->map(fn (ClinicalRecommendation $recommendation) => $recommendation->status->label())
                ->all();
        }

        if ($canChangeStatus) {
            $details['can_move_to'] = array_map(
                fn (CaseStatus $status) => ['value' => $status->value, 'label' => $status->label()],
                app(CaseStatusTransitionMap::class)->allowedFrom($case->status),
            );
        }

        return $this->json($details);
    }
}
